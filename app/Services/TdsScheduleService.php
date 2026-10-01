<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeTdsSchedule;
use App\Models\FinancialYear;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\PayrollRunEmployeeLine;
use App\Models\TaxRegimeSlab;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employee-wise 12-month TDS plan. HRMS can go live mid-year, so the auto-calculated
 * monthly TDS (IncomeTaxCalculationService::project()) can't know what the legacy payroll
 * already withheld. Instead HR generates a schedule for every FY month, edits any month
 * (e.g. enters TDS already deducted before go-live, or front-loads tax), and payroll then
 * deducts exactly the last saved amount — see IncomeTaxCalculationService::monthlyTdsForPayroll().
 *
 * Month classes when generating from $startMonth:
 *  - finalized/locked in an HRMS payroll run → actual TDS deducted, read-only;
 *  - before $startMonth (paid outside HRMS) → whatever HR saved there, counted as already deducted;
 *  - manually edited (unless overwriting) → kept as-is;
 *  - everything else → the remaining annual tax spread equally.
 */
class TdsScheduleService
{
    public function __construct(private readonly IncomeTaxCalculationService $incomeTax) {}

    /**
     * @return array{annual_tax: float, scheduled_total: float}
     */
    public function generate(Employee $employee, FinancialYear $financialYear, string $startMonth, bool $overwriteManual = false, ?User $user = null): array
    {
        $months = $this->incomeTax->financialYearMonths($financialYear);

        if (! in_array($startMonth, $months, true)) {
            throw ValidationException::withMessages(['start_month' => 'The start month must fall within the selected financial year.']);
        }

        $regime = $employee->selectedRegimeFor($financialYear) ?? TaxRegimeSlab::REGIME_OLD;
        $annualTax = $this->incomeTax->taxBreakdown(
            $employee, $financialYear, $regime, $this->incomeTax->fullYearIncome($employee, $financialYear)
        )['final_tax'];

        $existing = $this->scheduleFor($employee, $financialYear)->keyBy('payroll_month');
        $finalized = $this->finalizedTdsByMonth($employee, $months);

        $amounts = [];
        $manual = [];
        $spreadMonths = [];

        foreach ($months as $month) {
            $row = $existing->get($month);

            if (array_key_exists($month, $finalized)) {
                $amounts[$month] = $finalized[$month];
                $manual[$month] = (bool) $row?->is_manual;
            } elseif ($month < $startMonth || (! $overwriteManual && $row?->is_manual)) {
                $amounts[$month] = (float) ($row?->amount ?? 0);
                $manual[$month] = (bool) $row?->is_manual;
            } else {
                $spreadMonths[] = $month;
                $manual[$month] = false;
            }
        }

        $remaining = max(0, round($annualTax - array_sum($amounts), 2));
        $count = count($spreadMonths);

        if ($count > 0) {
            // Whole rupees per month; the last month absorbs the remainder so the
            // schedule always adds up to the annual tax exactly.
            $perMonth = floor($remaining / $count);

            foreach ($spreadMonths as $i => $month) {
                $amounts[$month] = $i === $count - 1 ? round($remaining - $perMonth * ($count - 1), 2) : $perMonth;
            }
        }

        DB::transaction(function () use ($employee, $financialYear, $months, $amounts, $manual, $user) {
            foreach ($months as $month) {
                EmployeeTdsSchedule::updateOrCreate(
                    ['employee_id' => $employee->id, 'financial_year_id' => $financialYear->id, 'payroll_month' => $month],
                    ['amount' => $amounts[$month], 'is_manual' => $manual[$month], 'updated_by' => $user?->id],
                );
            }
        });

        return ['annual_tax' => $annualTax, 'scheduled_total' => round(array_sum($amounts), 2)];
    }

    public function updateAmount(EmployeeTdsSchedule $row, float $amount, ?User $user = null): EmployeeTdsSchedule
    {
        if ($amount < 0) {
            throw ValidationException::withMessages(['amount' => 'TDS amount cannot be negative.']);
        }

        if ($this->isLocked($row)) {
            throw ValidationException::withMessages(['amount' => "Payroll for {$row->payroll_month} is already finalized — its TDS can no longer be changed."]);
        }

        $row->update(['amount' => round($amount, 2), 'is_manual' => true, 'updated_by' => $user?->id]);

        return $row;
    }

    /**
     * A month is frozen once this employee has been paid in a finalized/locked payroll run
     * for it — the TDS has hit a payslip and must match what was actually deducted.
     */
    public function isLocked(EmployeeTdsSchedule $row): bool
    {
        return PayrollRunEmployee::query()
            ->where('employee_id', $row->employee_id)
            ->whereHas('payrollRun', fn ($q) => $q
                ->where('payroll_month', $row->payroll_month)
                ->whereIn('status', [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_LOCKED]))
            ->exists();
    }

    public function scheduleFor(Employee $employee, FinancialYear $financialYear)
    {
        return EmployeeTdsSchedule::query()
            ->where('employee_id', $employee->id)
            ->where('financial_year_id', $financialYear->id)
            ->orderBy('payroll_month')
            ->get();
    }

    /**
     * @param  string[]  $months
     * @return array<string, float> TDS actually deducted, keyed by month, for months with a finalized/locked run.
     */
    private function finalizedTdsByMonth(Employee $employee, array $months): array
    {
        $runEmployees = PayrollRunEmployee::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($q) => $q
                ->whereIn('payroll_month', $months)
                ->whereIn('status', [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_LOCKED]))
            ->with('payrollRun:id,payroll_month')
            ->get();

        $tdsByRunEmployee = PayrollRunEmployeeLine::query()
            ->whereIn('payroll_run_employee_id', $runEmployees->pluck('id'))
            ->where('label', IncomeTaxCalculationService::TDS_LABEL)
            ->groupBy('payroll_run_employee_id')
            ->selectRaw('payroll_run_employee_id, SUM(amount) as total')
            ->pluck('total', 'payroll_run_employee_id');

        $result = [];

        foreach ($runEmployees as $runEmployee) {
            $month = $runEmployee->payrollRun->payroll_month;
            $result[$month] = round(($result[$month] ?? 0) + (float) ($tdsByRunEmployee[$runEmployee->id] ?? 0), 2);
        }

        return $result;
    }
}
