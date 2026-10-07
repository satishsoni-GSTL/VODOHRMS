<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\EmployeeLoan;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $items = EmployeeLoan::query()
            ->with('approvalInstance')
            ->where('employee_id', $this->employee($request)->id)
            ->latest('request_date')
            ->get()
            ->map(fn (EmployeeLoan $l) => [
                'id' => $l->id,
                'type' => $l->type,
                'type_label' => $l->type === EmployeeLoan::TYPE_SALARY_ADVANCE ? 'Salary Advance' : 'Loan',
                'requested_amount' => (float) $l->requested_amount,
                'approved_amount' => $l->approved_amount !== null ? (float) $l->approved_amount : null,
                'installments' => $l->installments,
                'monthly_recovery' => $l->monthly_recovery !== null ? (float) $l->monthly_recovery : null,
                'outstanding_balance' => (float) ($l->outstanding_balance ?? 0),
                'request_date' => $l->request_date?->toDateString(),
                'reason' => $l->reason,
                'status' => $l->status,
                'status_label' => EmployeeLoan::STATUSES[$l->status] ?? self::statusLabel($l->status),
                'remarks' => self::latestRemarks($l->approvalInstance),
                'can_reapply' => in_array($l->status, [EmployeeLoan::STATUS_REJECTED, EmployeeLoan::STATUS_SENT_BACK], true),
            ]);

        return response()->json(['items' => $items]);
    }

    public function store(Request $request, LoanService $loans): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([EmployeeLoan::TYPE_LOAN, EmployeeLoan::TYPE_SALARY_ADVANCE])],
            'requested_amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $loan = $loans->request($this->employee($request), $data['type'], (float) $data['requested_amount'], $data['reason'], now()->toDateString());

        return response()->json(['ok' => true, 'message' => 'Request sent for approval.', 'id' => $loan->id], 201);
    }
}
