<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanRecovery;
use App\Models\PayrollRunEmployee;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(private readonly ApprovalWorkflowService $workflow) {}

    public function request(Employee $employee, string $type, float $amount, string $reason, string $requestDate): EmployeeLoan
    {
        $loan = EmployeeLoan::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'requested_amount' => $amount,
            'reason' => $reason,
            'request_date' => $requestDate,
            'status' => EmployeeLoan::STATUS_PENDING,
        ]);

        $this->workflow->submit($loan);

        return $loan->fresh();
    }

    /**
     * Recover this month's installment for one active loan against a payroll run, capped at
     * the outstanding balance (recovery can never exceed what's actually owed). Safe to call
     * repeatedly for the same payroll run (recalculation before finalization) — any prior
     * recovery recorded for this exact run is reversed and recomputed fresh first.
     */
    public function recoverForPayroll(EmployeeLoan $loan, PayrollRunEmployee $runEmployee, string $payrollMonth): float
    {
        return DB::transaction(function () use ($loan, $runEmployee, $payrollMonth) {
            $existing = $loan->recoveries()->where('payroll_run_employee_id', $runEmployee->id)->first();

            if ($existing) {
                $loan->increment('outstanding_balance', $existing->recovered_amount);
                $existing->delete();
            }

            $loan->refresh();

            if ($loan->status !== EmployeeLoan::STATUS_ACTIVE || (float) $loan->outstanding_balance <= 0) {
                return 0.0;
            }

            $amount = min((float) $loan->monthly_recovery, (float) $loan->outstanding_balance);

            if ($amount <= 0) {
                return 0.0;
            }

            EmployeeLoanRecovery::create([
                'employee_loan_id' => $loan->id,
                'payroll_run_employee_id' => $runEmployee->id,
                'recovered_amount' => $amount,
                'recovery_month' => $payrollMonth,
            ]);

            $loan->decrement('outstanding_balance', $amount);

            if ((float) $loan->fresh()->outstanding_balance <= 0) {
                $loan->update(['status' => EmployeeLoan::STATUS_CLOSED]);
            }

            return $amount;
        });
    }

    /**
     * @return array<int, array{loan: EmployeeLoan, amount: float}>
     */
    public function recoverAllForEmployee(Employee $employee, PayrollRunEmployee $runEmployee, string $payrollMonth): array
    {
        $results = [];

        foreach ($employee->loans()->where('status', EmployeeLoan::STATUS_ACTIVE)->get() as $loan) {
            $amount = $this->recoverForPayroll($loan, $runEmployee, $payrollMonth);

            if ($amount > 0) {
                $results[] = ['loan' => $loan, 'amount' => $amount];
            }
        }

        return $results;
    }
}
