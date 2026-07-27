<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FinancialYear;
use App\Models\PayrollRun;
use App\Services\PayrollCalculationService;
use App\Services\PayslipService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeneratePayrollForFinancialYear extends Command
{
    protected $signature = 'payroll:generate-fy
        {financial_year? : Financial year name, e.g. 2026-27 (defaults to the active financial year)}
        {--company= : Company ID (defaults to the only/first company)}
        {--assume-full-attendance : For any elapsed month with zero attendance records, backfill every working day as present before calculating}
        {--finalize : Finalize each run and generate payslips (otherwise runs stay in "calculated" status for review)}';

    protected $description = 'Generate (and optionally finalize + payslip) payroll runs for every elapsed month of a financial year, for all active employees.';

    public function handle(PayrollCalculationService $payroll, PayslipService $payslips): int
    {
        $financialYear = $this->argument('financial_year')
            ? FinancialYear::where('name', $this->argument('financial_year'))->first()
            : FinancialYear::where('is_active', true)->first();

        if (! $financialYear) {
            $this->error('Financial year not found.');

            return self::FAILURE;
        }

        $company = $this->option('company')
            ? Company::find($this->option('company'))
            : Company::query()->first();

        if (! $company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        $months = $this->monthsInRange($financialYear);
        $this->info("Financial Year {$financialYear->name} — {$company->name} — months: ".implode(', ', $months));

        if ($this->option('assume-full-attendance')) {
            $this->backfillAttendance($company, $months);
        }

        $summary = [];

        foreach ($months as $month) {
            $run = $payroll->getOrCreateRun($month, $company->id);

            if (! $run->isEditable()) {
                $summary[] = [$month, $run->status, '-', '-', '-', 'already '.$run->status];

                continue;
            }

            $payroll->calculate($run);
            $run->refresh();

            $totals = $run->employees()->selectRaw('SUM(gross_earnings) g, SUM(total_deductions) d, SUM(net_pay) n, COUNT(*) c')->first();

            $note = 'calculated';

            if ($this->option('finalize')) {
                $payroll->finalize($run);

                foreach ($run->employees as $runEmployee) {
                    $payslips->generate($runEmployee);
                }

                $note = 'finalized + payslips generated';
            }

            $summary[] = [$month, $totals->c ?? 0, number_format((float) $totals->g, 2), number_format((float) $totals->d, 2), number_format((float) $totals->n, 2), $note];
        }

        $this->newLine();
        $this->table(['Month', 'Employees', 'Gross', 'Deductions', 'Net Pay', 'Result'], $summary);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function monthsInRange(FinancialYear $fy): array
    {
        $start = $fy->start_date->copy()->startOfMonth();
        $end = min($fy->end_date->copy()->startOfMonth(), now()->startOfMonth());

        $months = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $months[] = $cursor->format('Y-m');
        }

        return $months;
    }

    private function backfillAttendance(Company $company, array $months): void
    {
        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD])
            ->get();

        DB::transaction(function () use ($employees, $months) {
            foreach ($employees as $employee) {
                if (empty($employee->weekly_off)) {
                    $employee->update(['weekly_off' => ['sunday']]);
                }
            }

            foreach ($months as $month) {
                $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $monthEnd = min((clone $monthStart)->endOfMonth(), now());

                $hasAnyAttendance = Attendance::query()
                    ->whereIn('employee_id', $employees->pluck('id'))
                    ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->exists();

                if ($hasAnyAttendance) {
                    continue;
                }

                foreach ($employees as $employee) {
                    $joinDate = $employee->date_of_joining ? Carbon::parse($employee->date_of_joining) : $monthStart;
                    $rangeStart = $joinDate->greaterThan($monthStart) ? $joinDate : $monthStart;

                    if ($rangeStart->greaterThan($monthEnd)) {
                        continue;
                    }

                    foreach (CarbonPeriod::create($rangeStart, $monthEnd) as $date) {
                        if (strtolower($date->format('l')) === 'sunday') {
                            continue;
                        }

                        Attendance::firstOrCreate(
                            ['employee_id' => $employee->id, 'attendance_date' => $date->toDateString()],
                            ['status' => Attendance::STATUS_PRESENT, 'source' => 'import', 'remarks' => 'Backfilled — no historical attendance available for this period'],
                        );
                    }
                }
            }
        });

        $this->info('Attendance backfilled for months with no existing records (Sundays treated as weekly off).');
    }
}
