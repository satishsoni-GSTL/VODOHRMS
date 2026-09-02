<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaimLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the month's expense picture two ways off one shared, team-scoped query:
 *
 *  - summary()  : one row per employee, one amount per expense head (category) plus a
 *                 row total, and a column-totals footer. Powers the on-screen
 *                 "Monthly Expense Summary" page and ExpenseMonthlySummaryExport.
 *  - dayWise()  : one row per expense claim line for a single employee, ordered by
 *                 date. Powers the "View" drill-down modal and ExpenseDayWiseExport.
 *
 * Amounts are the claimed (requested) amounts, matching the existing Expense report.
 * Visibility follows App\Filament\Concerns\ScopesToOwnTeam: holders of `expense.view`
 * see everyone, a manager sees their own team, anyone else sees only themselves.
 */
class ExpenseMonthlySummaryService
{
    /**
     * @return array{
     *     categories: array<int, string>,
     *     rows: array<int, array{employee_id:int, employee_code:string, employee_name:string, by_category:array<int, float>, total:float}>,
     *     totals: array<int, float>,
     *     grand_total: float,
     *     employee_ids: array<int, int>
     * }
     */
    public function summary(string $month, User $user): array
    {
        [$start, $end] = $this->monthBounds($month);

        $categories = ExpenseCategory::query()->active()->orderBy('name')->pluck('name', 'id')->all();

        $visibleIds = $this->visibleEmployeeIds($user);

        $grouped = ExpenseClaimLine::query()
            ->join('expense_claims', 'expense_claims.id', '=', 'expense_claim_lines.expense_claim_id')
            ->whereBetween('expense_claim_lines.expense_date', [$start->toDateString(), $end->toDateString()])
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('expense_claims.employee_id', $visibleIds))
            ->groupBy('expense_claims.employee_id', 'expense_claim_lines.category_id')
            ->selectRaw('expense_claims.employee_id, expense_claim_lines.category_id, SUM(expense_claim_lines.requested_amount) AS amount')
            ->get();

        if ($grouped->isEmpty()) {
            return [
                'categories' => $categories,
                'rows' => [],
                'totals' => array_fill_keys(array_keys($categories), 0.0),
                'grand_total' => 0.0,
                'employee_ids' => [],
            ];
        }

        $employees = Employee::query()
            ->whereIn('id', $grouped->pluck('employee_id')->unique()->all())
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'first_name', 'middle_name', 'last_name']);

        $byEmployee = $grouped->groupBy('employee_id');
        $totals = array_fill_keys(array_keys($categories), 0.0);
        $grandTotal = 0.0;
        $rows = [];

        foreach ($employees as $employee) {
            $byCategory = array_fill_keys(array_keys($categories), 0.0);
            $rowTotal = 0.0;

            foreach ($byEmployee->get($employee->id, collect()) as $line) {
                if (! array_key_exists($line->category_id, $byCategory)) {
                    continue; // inactive/removed category — folded into the total below
                }

                $amount = (float) $line->amount;
                $byCategory[$line->category_id] += $amount;
                $totals[$line->category_id] += $amount;
                $rowTotal += $amount;
                $grandTotal += $amount;
            }

            $rows[] = [
                'employee_id' => $employee->id,
                'employee_code' => (string) $employee->employee_code,
                'employee_name' => $employee->full_name,
                'by_category' => $byCategory,
                'total' => $rowTotal,
            ];
        }

        return [
            'categories' => $categories,
            'rows' => $rows,
            'totals' => $totals,
            'grand_total' => $grandTotal,
            'employee_ids' => $employees->pluck('id')->all(),
        ];
    }

    /**
     * Per-line detail for one employee in the month, oldest first.
     * Returns an empty collection when the employee is outside the user's scope.
     *
     * @return Collection<int, array{date:string, category:string, description:?string, vendor:?string, bill_number:?string, payment_mode:?string, requested_amount:float, approved_amount:?float, claim_number:string, status:string}>
     */
    public function dayWise(string $month, int $employeeId, User $user): Collection
    {
        [$start, $end] = $this->monthBounds($month);

        $visibleIds = $this->visibleEmployeeIds($user);

        if ($visibleIds !== null && ! in_array($employeeId, $visibleIds, true)) {
            return collect();
        }

        return ExpenseClaimLine::query()
            ->join('expense_claims', 'expense_claims.id', '=', 'expense_claim_lines.expense_claim_id')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expense_claim_lines.category_id')
            ->where('expense_claims.employee_id', $employeeId)
            ->whereBetween('expense_claim_lines.expense_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('expense_claim_lines.expense_date')
            ->orderBy('expense_claim_lines.id')
            ->get([
                'expense_claim_lines.expense_date',
                'expense_categories.name AS category_name',
                'expense_claim_lines.description',
                'expense_claim_lines.vendor',
                'expense_claim_lines.bill_number',
                'expense_claim_lines.payment_mode',
                'expense_claim_lines.requested_amount',
                'expense_claim_lines.approved_amount',
                'expense_claims.claim_number',
                'expense_claims.status',
            ])
            ->map(fn ($line) => [
                'date' => Carbon::parse($line->expense_date)->toDateString(),
                'category' => $line->category_name ?? '—',
                'description' => $line->description,
                'vendor' => $line->vendor,
                'bill_number' => $line->bill_number,
                'payment_mode' => $line->payment_mode,
                'requested_amount' => (float) $line->requested_amount,
                'approved_amount' => $line->approved_amount === null ? null : (float) $line->approved_amount,
                'claim_number' => (string) $line->claim_number,
                'status' => (string) $line->status,
            ]);
    }

    /**
     * @return array<int, int>|null null means "no restriction" (HR-tier)
     */
    private function visibleEmployeeIds(User $user): ?array
    {
        if ($user->can('expense.view')) {
            return null;
        }

        $employee = $user->employee;

        if (! $employee) {
            return [];
        }

        return [$employee->id, ...$employee->allSubordinateIds()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthBounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        return [$start, (clone $start)->endOfMonth()];
    }
}
