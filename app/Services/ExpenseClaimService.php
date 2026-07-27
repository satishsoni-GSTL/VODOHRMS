<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExpensePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseClaimService
{
    public function __construct(private readonly ApprovalWorkflowService $workflow) {}

    /**
     * @param  array<int, array{category_id:int, expense_date:string, requested_amount:float, description?:string, vendor?:string, bill_number?:string, payment_mode?:string, receipt_path?:string}>  $lines
     */
    public function submit(Employee $employee, string $claimDate, ?string $projectClient, array $lines): ExpenseClaim
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'At least one expense line is required.']);
        }

        return DB::transaction(function () use ($employee, $claimDate, $projectClient, $lines) {
            $claim = ExpenseClaim::create([
                'claim_number' => $this->nextClaimNumber(),
                'employee_id' => $employee->id,
                'claim_date' => $claimDate,
                'project_client' => $projectClient,
                'status' => ExpenseClaim::STATUS_DRAFT,
            ]);

            foreach ($lines as $line) {
                $claim->lines()->create($line);
            }

            $claim->recalculateTotals();
            $claim->save();

            $this->workflow->submit($claim);

            return $claim->fresh('lines');
        });
    }

    private function nextClaimNumber(): string
    {
        $prefix = 'EXP-'.now()->format('Ym').'-';
        $lastSequence = ExpenseClaim::query()
            ->where('claim_number', 'like', "{$prefix}%")
            ->count();

        return $prefix.str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    public function recordPayment(ExpenseClaim $claim, float $amount, string $paidOn, ?string $reference, int $paidByUserId): ExpensePayment
    {
        if ($claim->status !== ExpenseClaim::STATUS_APPROVED) {
            throw ValidationException::withMessages(['status' => 'Only approved claims can be paid.']);
        }

        if ($claim->payment()->exists()) {
            throw ValidationException::withMessages(['status' => 'This claim has already been paid.']);
        }

        return DB::transaction(function () use ($claim, $amount, $paidOn, $reference, $paidByUserId) {
            $payment = ExpensePayment::create([
                'expense_claim_id' => $claim->id,
                'paid_amount' => $amount,
                'paid_on' => $paidOn,
                'payment_reference' => $reference,
                'paid_by' => $paidByUserId,
            ]);

            $claim->update(['status' => ExpenseClaim::STATUS_PAID]);

            return $payment;
        });
    }
}
