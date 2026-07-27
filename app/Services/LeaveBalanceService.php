<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveBalanceService
{
    public function balanceFor(Employee $employee, LeaveType $leaveType, int $year): EmployeeLeaveBalance
    {
        return EmployeeLeaveBalance::firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
            ['opening_balance' => 0, 'credited' => 0, 'adjusted' => 0, 'used' => 0, 'lapsed' => 0, 'encashed' => 0, 'closing_balance' => 0]
        );
    }

    public function credit(Employee $employee, LeaveType $leaveType, int $year, float $days, string $remarks = 'Credit'): EmployeeLeaveBalance
    {
        return DB::transaction(function () use ($employee, $leaveType, $year, $days, $remarks) {
            $balance = $this->balanceFor($employee, $leaveType, $year);
            $balance->credited += $days;
            $balance->recalculateClosingBalance();
            $balance->save();

            $this->writeLedgerEntry($employee, $leaveType, LeaveLedgerEntry::TYPE_CREDIT, $days, $balance->closing_balance, $remarks);

            return $balance;
        });
    }

    public function debitForApprovedLeave(LeaveApplication $application): void
    {
        DB::transaction(function () use ($application) {
            $employee = $application->employee;
            $leaveType = $application->leaveType;
            $year = $application->from_date->year;
            $balance = $this->balanceFor($employee, $leaveType, $year);

            if (! $leaveType->allow_negative_balance && ($balance->closing_balance - $application->days) < 0) {
                throw ValidationException::withMessages([
                    'days' => "Insufficient {$leaveType->name} balance ({$balance->closing_balance} available, {$application->days} requested).",
                ]);
            }

            $balance->used += $application->days;
            $balance->recalculateClosingBalance();
            $balance->save();

            $this->writeLedgerEntry(
                $employee, $leaveType, LeaveLedgerEntry::TYPE_DEBIT, $application->days, $balance->closing_balance,
                "Leave application #{$application->id}", LeaveApplication::class, $application->id
            );
        });
    }

    public function reverseForCancelledLeave(LeaveApplication $application): void
    {
        DB::transaction(function () use ($application) {
            $employee = $application->employee;
            $leaveType = $application->leaveType;
            $year = $application->from_date->year;
            $balance = $this->balanceFor($employee, $leaveType, $year);

            $balance->used = max(0, $balance->used - $application->days);
            $balance->recalculateClosingBalance();
            $balance->save();

            $this->writeLedgerEntry(
                $employee, $leaveType, LeaveLedgerEntry::TYPE_ADJUSTMENT, $application->days, $balance->closing_balance,
                "Reversal for leave application #{$application->id}", LeaveApplication::class, $application->id
            );
        });
    }

    private function writeLedgerEntry(
        Employee $employee,
        LeaveType $leaveType,
        string $type,
        float $days,
        float $balanceAfter,
        string $remarks,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): LeaveLedgerEntry {
        return LeaveLedgerEntry::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'entry_date' => now()->toDateString(),
            'type' => $type,
            'days' => $days,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'remarks' => $remarks,
        ]);
    }
}
