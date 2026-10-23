<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\PayrollRunEmployee;
use App\Models\SalaryComponent;
use App\Services\PayrollCalculationService;
use App\Services\PayslipService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off restatement of payroll generated before LOP became a deduction line. Those rows
 * prorated earnings by paid days; this re-states prorated earnings for the full employed
 * period and adds a "Loss of Pay (LOP)" deduction for the difference. Net pay is unchanged
 * (the LOP line equals exactly what was added to earnings). Existing payslip PDFs are
 * regenerated. Runs as a dry run unless --commit is passed.
 */
class RestateLopAsDeduction extends Command
{
    protected $signature = 'payroll:restate-lop
        {--month= : Only this payroll month (YYYY-MM).}
        {--commit : Persist the changes. Without this flag the command only reports what it would do.}';

    protected $description = 'Move LOP from prorated earnings to a separate LOP deduction line on already-generated payroll.';

    public function handle(PayslipService $payslips): int
    {
        $commit = (bool) $this->option('commit');

        $rows = PayrollRunEmployee::query()
            ->where('lop_days', '>', 0)
            ->whereDoesntHave('lines', fn ($q) => $q->where('label', PayrollCalculationService::LOP_LABEL))
            ->when($this->option('month'), fn ($q, $month) => $q->whereHas('payrollRun', fn ($r) => $r->where('payroll_month', $month)))
            ->with(['payrollRun', 'employee', 'payslip', 'lines.component', 'salaryStructure.lines'])
            ->get();

        $report = [];

        foreach ($rows as $row) {
            $daysInMonth = Carbon::createFromFormat('Y-m-d', $row->payrollRun->payroll_month.'-01')->daysInMonth;
            $paidDays = (float) $row->paid_days;
            $employedDays = $paidDays + (float) $row->lop_days;
            $monthly = $row->salaryStructure?->lines->pluck('monthly_amount', 'salary_component_id') ?? collect();

            $updates = [];

            foreach ($row->lines as $line) {
                if ($line->component_type !== SalaryComponent::TYPE_EARNING || ! $line->component?->is_prorated) {
                    continue;
                }

                $full = $monthly->get($line->salary_component_id);

                $newAmount = match (true) {
                    $full !== null => round((float) $full * $employedDays / $daysInMonth, 2),
                    $paidDays > 0 => round((float) $line->amount * $employedDays / $paidDays, 2),
                    default => null,
                };

                if ($newAmount !== null && $newAmount > (float) $line->amount) {
                    $updates[] = [$line, $newAmount];
                }
            }

            $lopAmount = round(array_sum(array_map(fn ($u) => $u[1] - (float) $u[0]->amount, $updates)), 2);

            if ($lopAmount <= 0) {
                continue;
            }

            $report[] = [
                $row->payrollRun->payroll_month,
                $row->employee?->employee_code,
                $row->employee?->full_name,
                $row->lop_days,
                number_format((float) $row->gross_earnings, 2).' → '.number_format((float) $row->gross_earnings + $lopAmount, 2),
                number_format($lopAmount, 2),
                number_format((float) $row->net_pay, 2),
            ];

            if (! $commit) {
                continue;
            }

            DB::transaction(function () use ($row, $updates, $lopAmount) {
                foreach ($updates as [$line, $newAmount]) {
                    $line->update(['amount' => $newAmount]);
                }

                $row->lines()->create([
                    'salary_component_id' => null,
                    'label' => PayrollCalculationService::LOP_LABEL,
                    'amount' => $lopAmount,
                    'component_type' => SalaryComponent::TYPE_DEDUCTION,
                ]);

                $row->update([
                    'gross_earnings' => round((float) $row->gross_earnings + $lopAmount, 2),
                    'total_deductions' => round((float) $row->total_deductions + $lopAmount, 2),
                    'lop_amount' => $lopAmount,
                ]);
            });

            if ($row->payslip) {
                // Payslips of since-deleted (soft-deleted) employees are regenerated too.
                $fresh = $row->fresh();
                $fresh->setRelation('employee', Employee::withTrashed()->find($row->employee_id));
                $payslips->generate($fresh);
            }
        }

        $this->table(['Month', 'Code', 'Employee', 'LOP Days', 'Gross (old → new)', 'LOP Deduction', 'Net Pay (unchanged)'], $report);
        $this->info(count($report).' payroll row(s) '.($commit ? 'restated; payslips regenerated.' : 'would be restated. Re-run with --commit to apply.'));

        return self::SUCCESS;
    }
}
