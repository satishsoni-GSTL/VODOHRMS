<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeLoan;
use App\Models\ExpenseClaim;
use App\Models\FullFinalSettlement;
use App\Models\Resignation;
use App\Models\User;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\FnfSettlementNotification;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class FnFSettlementService
{
    use NotifiesRecipients;

    /**
     * Compute (or recompute) the F&F draft from whatever real data currently exists —
     * leave encashment, outstanding loans/advances, and approved-but-unpaid expenses are
     * pulled automatically; pending salary / bonus / other lines remain HR-editable since
     * they depend on a final payroll run that may not exist yet for the exit month.
     */
    public function calculate(Resignation $resignation, ?User $calculatedBy = null): FullFinalSettlement
    {
        $employee = $resignation->employee;
        $lastWorkingDate = $resignation->approved_last_working_date ?? $resignation->requested_last_working_date;

        $settlement = FullFinalSettlement::firstOrNew(['resignation_id' => $resignation->id]);
        $settlement->employee_id = $employee->id;

        $dailyRate = ((float) ($employee->currentSalaryStructure()?->monthly_gross ?? 0)) / 30;

        $encashableBalance = (float) EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', Carbon::parse($lastWorkingDate)->year)
            ->whereHas('leaveType', fn ($q) => $q->where('encashment_allowed', true))
            ->sum('closing_balance');
        $settlement->leave_encashment = round($encashableBalance * $dailyRate, 2);

        $settlement->loan_recovery = (float) $employee->loans()
            ->where('type', EmployeeLoan::TYPE_LOAN)
            ->where('status', EmployeeLoan::STATUS_ACTIVE)
            ->sum('outstanding_balance');

        $settlement->advance_recovery = (float) $employee->loans()
            ->where('type', EmployeeLoan::TYPE_SALARY_ADVANCE)
            ->where('status', EmployeeLoan::STATUS_ACTIVE)
            ->sum('outstanding_balance');

        $settlement->reimbursement = (float) $employee->expenseClaims()
            ->where('status', ExpenseClaim::STATUS_APPROVED)
            ->sum('total_approved_amount');

        $settlement->notice_recovery = $this->calculateNoticeShortfallRecovery($resignation, $dailyRate);

        $settlement->pending_salary ??= 0;
        $settlement->bonus_incentive ??= 0;
        $settlement->other_earnings ??= 0;
        $settlement->tds ??= 0;
        $settlement->other_deductions ??= 0;

        $settlement->status = FullFinalSettlement::STATUS_CALCULATED;
        $settlement->calculated_by = $calculatedBy?->id;
        $settlement->recalculateFinalAmount();
        $settlement->save();

        return $settlement;
    }

    private function calculateNoticeShortfallRecovery(Resignation $resignation, float $dailyRate): float
    {
        if (! $resignation->notice_period_days) {
            return 0.0;
        }

        $requiredLastDay = Carbon::parse($resignation->resignation_date)->addDays($resignation->notice_period_days);
        $actualLastDay = Carbon::parse($resignation->approved_last_working_date ?? $resignation->requested_last_working_date);

        if ($actualLastDay->gte($requiredLastDay)) {
            return 0.0;
        }

        $shortfallDays = $requiredLastDay->diffInDays($actualLastDay);

        return round($shortfallDays * $dailyRate, 2);
    }

    public function approve(FullFinalSettlement $settlement, User $user): void
    {
        if ($settlement->status !== FullFinalSettlement::STATUS_CALCULATED) {
            throw ValidationException::withMessages(['status' => 'Only a calculated settlement can be approved.']);
        }

        $settlement->update(['status' => FullFinalSettlement::STATUS_APPROVED, 'approved_by' => $user->id]);

        $this->notifyEmployee($settlement->employee, new FnfSettlementNotification($settlement, 'approved'));
    }

    public function markPaid(FullFinalSettlement $settlement): void
    {
        if ($settlement->status !== FullFinalSettlement::STATUS_APPROVED) {
            throw ValidationException::withMessages(['status' => 'Only an approved settlement can be marked paid.']);
        }

        $settlement->update(['status' => FullFinalSettlement::STATUS_PAID, 'paid_at' => now()]);
        $settlement->employee->update(['status' => Employee::STATUS_EXITED]);

        $this->notifyEmployee($settlement->employee, new FnfSettlementNotification($settlement, 'paid'));
    }
}
