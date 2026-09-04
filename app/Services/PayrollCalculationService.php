<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\PayrollInput;
use App\Models\PayrollRun;
use App\Models\PayrollRunDeductionException;
use App\Models\PayrollRunEmployee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\PayslipReadyNotification;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollCalculationService
{
    use NotifiesRecipients;

    /**
     * Weekly-off applied when an employee has no `weekly_off` configured, so Saturdays and
     * Sundays are treated as paid weekly-offs (never LOP). Mirrors WorkFromHomeService.
     */
    private const DEFAULT_WEEKLY_OFF = ['saturday', 'sunday'];

    public function __construct(
        private readonly IncomeTaxCalculationService $incomeTax,
        private readonly LoanService $loans,
        private readonly AuditLogService $auditLog,
        private readonly PrePayrollDeductionService $deductionExceptions,
    ) {}

    public function getOrCreateRun(string $payrollMonth, int $companyId): PayrollRun
    {
        return PayrollRun::firstOrCreate(
            ['payroll_month' => $payrollMonth, 'company_id' => $companyId],
            ['status' => PayrollRun::STATUS_DRAFT]
        );
    }

    /**
     * Calculate (or recalculate) every active employee in this run. Safe to call repeatedly
     * before finalization — each call replaces the employee's lines with a fresh calculation.
     */
    public function calculate(PayrollRun $run): void
    {
        if (! $run->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'This payroll run is finalized/locked. Reopen it before recalculating.',
            ]);
        }

        $employees = Employee::query()
            ->where('company_id', $run->company_id)
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD])
            ->get();

        $waived = $this->deductionExceptions->waivedKeySet($run);

        DB::transaction(function () use ($run, $employees, $waived) {
            foreach ($employees as $employee) {
                $this->calculateForEmployee($run, $employee, $waived);
            }

            $run->update(['status' => PayrollRun::STATUS_CALCULATED]);
        });
    }

    /**
     * @param  array<string, true>|null  $waived  Deduction-exception key set for this run;
     *                                            resolved from PrePayrollDeductionService when null.
     */
    public function calculateForEmployee(PayrollRun $run, Employee $employee, ?array $waived = null): PayrollRunEmployee
    {
        $waived ??= $this->deductionExceptions->waivedKeySet($run);

        $monthStart = Carbon::createFromFormat('Y-m', $run->payroll_month)->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();
        $totalDaysInMonth = $monthStart->daysInMonth;

        $structure = $employee->salaryStructures()
            ->where('effective_from', '<=', $monthEnd->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        [$paidDays, $lopDays] = $this->calculatePaidDays($employee, $monthStart, $monthEnd);
        $prorationFactor = $totalDaysInMonth > 0 ? $paidDays / $totalDaysInMonth : 0;

        $runEmployee = PayrollRunEmployee::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            [
                'salary_structure_id' => $structure?->id,
                'paid_days' => $paidDays,
                'lop_days' => $lopDays,
                'gross_earnings' => 0,
                'total_deductions' => 0,
                'employer_contributions' => 0,
                'net_pay' => 0,
                'status' => 'calculated',
            ]
        );

        $runEmployee->lines()->delete();

        $grossEarnings = 0.0;
        $totalDeductions = 0.0;
        $employerContributions = 0.0;
        $fullMonthProratable = 0.0;

        if ($structure) {
            foreach ($structure->lines()->with('component')->get() as $line) {
                $component = $line->component;

                // A deduction HR has waived for this run only — skip it entirely.
                if ($component->type === SalaryComponent::TYPE_DEDUCTION
                    && $this->deductionIsWaived($waived, $component, $line->id)) {
                    continue;
                }

                $amount = $component->is_prorated
                    ? round((float) $line->monthly_amount * $prorationFactor, 2)
                    : (float) $line->monthly_amount;

                if ($component->type === SalaryComponent::TYPE_EARNING && $component->is_prorated) {
                    $fullMonthProratable += (float) $line->monthly_amount;
                }

                $runEmployee->lines()->create([
                    'salary_component_id' => $component->id,
                    'label' => $component->name,
                    'amount' => $amount,
                    'component_type' => $component->type,
                ]);

                match ($component->type) {
                    SalaryComponent::TYPE_EARNING => $grossEarnings += $amount,
                    SalaryComponent::TYPE_DEDUCTION => $totalDeductions += $amount,
                    SalaryComponent::TYPE_EMPLOYER_CONTRIBUTION => $employerContributions += $amount,
                    default => null,
                };
            }
        }

        $lopAmount = round($fullMonthProratable * (1 - $prorationFactor), 2);

        foreach (PayrollInput::where('employee_id', $employee->id)->where('payroll_month', $run->payroll_month)->get() as $input) {
            $amount = (float) $input->amount;
            $isEarning = in_array($input->type, PayrollInput::EARNING_TYPES, true);

            if (! $isEarning && isset($waived[PayrollRunDeductionException::keyFor(
                PayrollRunDeductionException::SOURCE_PAYROLL_INPUT, $input->id, null
            )])) {
                continue;
            }

            $runEmployee->lines()->create([
                'salary_component_id' => null,
                'label' => PayrollInput::TYPES[$input->type] ?? $input->type,
                'amount' => $amount,
                'component_type' => $isEarning ? SalaryComponent::TYPE_EARNING : SalaryComponent::TYPE_DEDUCTION,
            ]);

            if ($isEarning) {
                $grossEarnings += $amount;
            } else {
                $totalDeductions += $amount;
            }
        }

        // Persist gross earnings before projecting tax, since the projection sums this
        // month's gross_earnings from the database to build year-to-date income.
        $runEmployee->update(['gross_earnings' => round($grossEarnings, 2)]);

        $financialYear = $this->incomeTax->financialYearForMonth($run->payroll_month);

        $tdsWaived = isset($waived[PayrollRunDeductionException::keyFor(
            PayrollRunDeductionException::SOURCE_STATUTORY, null, PrePayrollDeductionService::TDS_CODE
        )]);

        if ($financialYear && ! $tdsWaived) {
            $tds = $this->incomeTax->monthlyTdsForPayroll($employee, $financialYear, $run->payroll_month);

            if ($tds > 0) {
                $runEmployee->lines()->create([
                    'salary_component_id' => null,
                    'label' => IncomeTaxCalculationService::TDS_LABEL,
                    'amount' => $tds,
                    'component_type' => SalaryComponent::TYPE_DEDUCTION,
                ]);

                $totalDeductions += $tds;
            }
        }

        foreach ($this->loans->recoverAllForEmployee($employee, $runEmployee, $run->payroll_month) as $recovery) {
            $runEmployee->lines()->create([
                'salary_component_id' => null,
                'label' => $recovery['loan']->type === EmployeeLoan::TYPE_SALARY_ADVANCE ? 'Salary Advance Recovery' : 'Loan Recovery',
                'amount' => $recovery['amount'],
                'component_type' => SalaryComponent::TYPE_DEDUCTION,
            ]);

            $totalDeductions += $recovery['amount'];
        }

        $runEmployee->update([
            'lop_amount' => $lopAmount,
            'total_deductions' => round($totalDeductions, 2),
            'employer_contributions' => round($employerContributions, 2),
            'net_pay' => round($grossEarnings - $totalDeductions, 2),
        ]);

        return $runEmployee->fresh('lines');
    }

    /**
     * Whether a salary-structure deduction line is waived for this run — statutory
     * components are keyed by code (survives a mid-month revision), the rest by line id.
     *
     * @param  array<string, true>  $waived
     */
    private function deductionIsWaived(array $waived, SalaryComponent $component, int $lineId): bool
    {
        if (PrePayrollDeductionService::isStatutoryCode($component->code)) {
            return isset($waived[PayrollRunDeductionException::keyFor(
                PayrollRunDeductionException::SOURCE_STATUTORY, null, strtoupper((string) $component->code)
            )]);
        }

        return isset($waived[PayrollRunDeductionException::keyFor(
            PayrollRunDeductionException::SOURCE_STRUCTURE_LINE, $lineId, null
        )]);
    }

    /**
     * @return array{0: float, 1: float} [paidDays, lopDays]
     */
    private function calculatePaidDays(Employee $employee, Carbon $monthStart, Carbon $monthEnd): array
    {
        $weeklyOff = collect($employee->weekly_off ?: self::DEFAULT_WEEKLY_OFF)->map(fn ($d) => strtolower($d));

        $holidays = Holiday::query()
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $attendanceByDate = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $a) => $a->attendance_date->toDateString());

        $approvedLeaveDates = [];
        LeaveApplication::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveApplication::STATUS_APPROVED)
            ->where('from_date', '<=', $monthEnd->toDateString())
            ->where('to_date', '>=', $monthStart->toDateString())
            ->with('leaveType')
            ->get()
            ->each(function (LeaveApplication $application) use (&$approvedLeaveDates) {
                foreach (CarbonPeriod::create($application->from_date, $application->to_date) as $date) {
                    $approvedLeaveDates[$date->toDateString()] = $application->is_half_day ? 0.5 : ($application->leaveType->is_paid_leave ? 1.0 : 0.0);
                }
            });

        $paidDays = 0.0;
        $lopDays = 0.0;

        foreach (CarbonPeriod::create($monthStart, $monthEnd) as $date) {
            $dateString = $date->toDateString();

            if (in_array(strtolower($date->format('l')), $weeklyOff->all(), true) || in_array($dateString, $holidays, true)) {
                $paidDays += 1.0;

                continue;
            }

            if (array_key_exists($dateString, $approvedLeaveDates)) {
                $portion = $approvedLeaveDates[$dateString];
                $paidDays += $portion;
                $lopDays += (1.0 - $portion);

                continue;
            }

            $attendance = $attendanceByDate->get($dateString);

            // At least one punch on the day counts as a full present day — HR treats LOP as
            // presence-based, not hours-based — so a forgotten out-punch (status still
            // "absent"/"missing_punch") is not docked.
            $hasAnyPunch = $attendance && ($attendance->first_in || $attendance->last_out);

            $portion = match ($attendance?->status) {
                Attendance::STATUS_PRESENT, Attendance::STATUS_WFH, Attendance::STATUS_ON_DUTY => 1.0,
                Attendance::STATUS_HALF_DAY => 0.5,
                default => $hasAnyPunch ? 1.0 : 0.0,
            };

            $paidDays += $portion;
            $lopDays += (1.0 - $portion);
        }

        return [round($paidDays, 2), round($lopDays, 2)];
    }

    public function finalize(PayrollRun $run): void
    {
        if ($run->status !== PayrollRun::STATUS_CALCULATED && $run->status !== PayrollRun::STATUS_REVIEWED) {
            throw ValidationException::withMessages(['status' => 'Only a calculated/reviewed payroll run can be finalized.']);
        }

        DB::transaction(function () use ($run) {
            $run->employees()->update(['status' => 'finalized']);

            Attendance::query()
                ->whereIn('employee_id', $run->employees()->pluck('employee_id'))
                ->whereBetween('attendance_date', $this->monthBounds($run->payroll_month))
                ->update(['is_frozen' => true]);

            $run->update(['status' => PayrollRun::STATUS_FINALIZED]);
        });

        $this->auditLog->log('finalize', $run, [], ['status' => PayrollRun::STATUS_FINALIZED], module: 'payroll');

        $notification = new PayslipReadyNotification($run);

        $run->employees()->with('employee.user')->get()->each(
            fn (PayrollRunEmployee $runEmployee) => $runEmployee->employee
                ? $this->notifyEmployee($runEmployee->employee, $notification)
                : null
        );
    }

    public function lock(PayrollRun $run, User $user): void
    {
        if ($run->status !== PayrollRun::STATUS_FINALIZED) {
            throw ValidationException::withMessages(['status' => 'Only a finalized payroll run can be locked.']);
        }

        $run->update([
            'status' => PayrollRun::STATUS_LOCKED,
            'locked_at' => now(),
            'locked_by' => $user->id,
        ]);

        $this->auditLog->log('lock', $run, [], ['status' => PayrollRun::STATUS_LOCKED], module: 'payroll');
    }

    public function reopen(PayrollRun $run, string $reason): void
    {
        if (! in_array($run->status, [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_LOCKED], true)) {
            throw ValidationException::withMessages(['status' => 'Only a finalized/locked payroll run can be reopened.']);
        }

        DB::transaction(function () use ($run, $reason) {
            Attendance::query()
                ->whereIn('employee_id', $run->employees()->pluck('employee_id'))
                ->whereBetween('attendance_date', $this->monthBounds($run->payroll_month))
                ->update(['is_frozen' => false]);

            $run->update([
                'status' => PayrollRun::STATUS_REOPENED,
                'reopened_reason' => $reason,
            ]);
        });

        $this->auditLog->log('reopen', $run, [], ['status' => PayrollRun::STATUS_REOPENED], reason: $reason, module: 'payroll');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthBounds(string $payrollMonth): array
    {
        $start = Carbon::createFromFormat('Y-m', $payrollMonth)->startOfMonth();

        return [$start->toDateString(), (clone $start)->endOfMonth()->toDateString()];
    }
}
