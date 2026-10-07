<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\ExpenseClaimLine;
use App\Services\ApprovalWorkflowService;
use App\Services\ExpenseClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends MobileController
{
    private const PAYMENT_MODES = ['cash' => 'Cash', 'card' => 'Card', 'upi' => 'UPI', 'other' => 'Other'];

    public function index(Request $request): JsonResponse
    {
        $claims = ExpenseClaim::query()
            ->withCount('lines')
            ->where('employee_id', $this->employee($request)->id)
            ->latest('claim_date')
            ->limit(100)
            ->get()
            ->map(fn (ExpenseClaim $c) => [
                'id' => $c->id,
                'claim_number' => $c->claim_number,
                'claim_date' => $c->claim_date?->toDateString(),
                'project_client' => $c->project_client,
                'items' => $c->lines_count,
                'total_requested' => (float) $c->total_requested_amount,
                'total_approved' => (float) $c->total_approved_amount,
                'status' => $c->status,
                'status_label' => ExpenseClaim::STATUSES[$c->status] ?? self::statusLabel($c->status),
            ]);

        return response()->json([
            'categories' => ExpenseCategory::query()->active()->orderBy('name')->get()
                ->map(fn (ExpenseCategory $c) => ['id' => $c->id, 'name' => $c->name, 'requires_bill' => (bool) $c->requires_bill, 'requires_project' => (bool) $c->requires_project]),
            'payment_modes' => collect(self::PAYMENT_MODES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'claims' => $claims,
        ]);
    }

    public function show(Request $request, ExpenseClaim $claim): JsonResponse
    {
        abort_unless($claim->employee_id === $this->employee($request)->id, 404);

        $claim->load(['lines.category', 'payment', 'approvalInstance']);

        return response()->json(['claim' => self::presentClaim($claim, $request)]);
    }

    public function store(Request $request, ExpenseClaimService $service): JsonResponse
    {
        $data = $this->validateClaim($request);

        $claim = $service->submit($this->employee($request), $data['claim_date'], $data['project_client'] ?? null, $this->lines($request, $data));

        return response()->json([
            'ok' => true,
            'message' => "Claim {$claim->claim_number} submitted for approval.",
            'claim' => self::presentClaim($claim->load(['lines.category', 'payment', 'approvalInstance']), $request),
        ], 201);
    }

    /** Correct a sent-back claim and resubmit it (lines are replaced, as on the web). */
    public function resubmit(Request $request, ExpenseClaim $claim, ExpenseClaimService $service): JsonResponse
    {
        abort_unless($claim->employee_id === $this->employee($request)->id, 404);
        abort_unless($claim->isEditableBy($this->user($request)), 422, 'Only a sent-back claim can be corrected and resubmitted.');

        $data = $this->validateClaim($request);
        $claim = $service->resubmit($claim, $data['claim_date'], $data['project_client'] ?? null, $this->lines($request, $data, $claim));

        return response()->json([
            'ok' => true,
            'message' => "Claim {$claim->claim_number} resubmitted for approval.",
            'claim' => self::presentClaim($claim->load(['lines.category', 'payment', 'approvalInstance']), $request),
        ]);
    }

    /** My monthly expense statement: summary + every bill in one PDF, ready to share. */
    public function statement(Request $request, \App\Services\ExpenseStatementService $statements): \Illuminate\Http\Response
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $employee = $this->employee($request);
        $month = $request->query('month');

        return response($statements->build($employee, $month), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$statements->fileName($employee, $month).'"',
        ]);
    }

    /** Receipt image/PDF for one of my own lines (or one I may approve). */
    public function receipt(Request $request, ExpenseClaimLine $line): StreamedResponse
    {
        $user = $this->user($request);
        $claim = $line->claim;

        $canView = $claim->employee_id === $user->employee_id
            || ($claim->approval_instance_id && app(ApprovalWorkflowService::class)->canUserActOnInstance($claim->approvalInstance, $user))
            || $user->can('expense.view');

        abort_unless($canView, 403);
        abort_unless($line->receipt_path && Storage::disk(self::DISK)->exists($line->receipt_path), 404);

        return Storage::disk(self::DISK)->download($line->receipt_path);
    }

    /** @return array<string, mixed> */
    private function validateClaim(Request $request): array
    {
        return $request->validate([
            'claim_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'project_client' => ['nullable', 'string', 'max:150'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.category_id' => ['required', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'lines.*.expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'lines.*.requested_amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'lines.*.description' => ['nullable', 'string', 'max:2000'],
            'lines.*.vendor' => ['nullable', 'string', 'max:150'],
            'lines.*.bill_number' => ['nullable', 'string', 'max:100'],
            'lines.*.payment_mode' => ['nullable', Rule::in(array_keys(self::PAYMENT_MODES))],
            'lines.*.receipt' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,heic,webp'],
            // On resubmit: keep the receipt already attached to this line of the same claim.
            'lines.*.existing_line_id' => ['nullable', 'integer'],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function lines(Request $request, array $data, ?ExpenseClaim $existing = null): array
    {
        $existingReceipts = $existing ? $existing->lines()->pluck('receipt_path', 'id') : collect();

        return collect($data['lines'])->map(function (array $line, int $i) use ($request, $existingReceipts) {
            $receipt = $this->storeUpload($request->file("lines.{$i}.receipt"), 'expense-receipts')
                ?? (isset($line['existing_line_id']) ? $existingReceipts->get((int) $line['existing_line_id']) : null);

            return [
                'category_id' => (int) $line['category_id'],
                'expense_date' => $line['expense_date'],
                'requested_amount' => (float) $line['requested_amount'],
                'description' => $line['description'] ?? null,
                'vendor' => $line['vendor'] ?? null,
                'bill_number' => $line['bill_number'] ?? null,
                'payment_mode' => $line['payment_mode'] ?? null,
                'receipt_path' => $receipt,
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private static function presentClaim(ExpenseClaim $claim, Request $request): array
    {
        return [
            'id' => $claim->id,
            'claim_number' => $claim->claim_number,
            'claim_date' => $claim->claim_date?->toDateString(),
            'project_client' => $claim->project_client,
            'total_requested' => (float) $claim->total_requested_amount,
            'total_approved' => (float) $claim->total_approved_amount,
            'status' => $claim->status,
            'status_label' => ExpenseClaim::STATUSES[$claim->status] ?? self::statusLabel($claim->status),
            'can_resubmit' => $claim->isEditableBy($request->user()),
            'paid' => $claim->payment ? [
                'amount' => (float) $claim->payment->paid_amount,
                'paid_on' => $claim->payment->paid_on?->toDateString(),
                'reference' => $claim->payment->payment_reference,
            ] : null,
            'lines' => $claim->lines->map(fn (ExpenseClaimLine $l) => [
                'id' => $l->id,
                'category_id' => $l->category_id,
                'category' => $l->category?->name,
                'expense_date' => $l->expense_date?->toDateString(),
                'requested_amount' => (float) $l->requested_amount,
                'approved_amount' => $l->approved_amount !== null ? (float) $l->approved_amount : null,
                'description' => $l->description,
                'vendor' => $l->vendor,
                'bill_number' => $l->bill_number,
                'payment_mode' => $l->payment_mode,
                'has_receipt' => (bool) $l->receipt_path,
            ])->values(),
            'approval_trail' => self::approvalTrail($claim->approvalInstance),
        ];
    }
}
