<?php

namespace App\Services;

use App\Models\ApprovalInstance;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\TravelRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Travel requests: pre-approval of a trip (manager, plus Finance when an advance is asked
 * for), payment of the travel advance by Finance, and settlement — the advance is netted
 * off the expense claim(s) linked to the trip when those claims are paid.
 */
class TravelService
{
    public function __construct(private readonly ApprovalWorkflowService $workflow) {}

    /**
     * @param  array{purpose: string, travel_type?: string, travel_mode: string, from_location: string, to_location: string, departure_date: string, return_date: string, accommodation_required?: bool, estimated_cost?: float|string|null, advance_requested?: float|string|null}  $data
     */
    public function request(Employee $employee, array $data, ?TravelRequest $replacing = null): TravelRequest
    {
        $departure = Carbon::parse($data['departure_date'])->startOfDay();
        $return = Carbon::parse($data['return_date'])->startOfDay();

        if ($return->lessThan($departure)) {
            throw ValidationException::withMessages(['return_date' => 'Return date cannot be before the departure date.']);
        }

        if ($departure->lessThan(Carbon::today())) {
            throw ValidationException::withMessages(['departure_date' => 'Travel must be requested before the trip. For a past trip, file an expense claim directly.']);
        }

        $estimated = round((float) ($data['estimated_cost'] ?? 0), 2);
        $advance = round((float) ($data['advance_requested'] ?? 0), 2);

        if ($advance < 0 || $estimated < 0) {
            throw ValidationException::withMessages(['advance_requested' => 'Amounts cannot be negative.']);
        }

        if ($estimated > 0 && $advance > $estimated) {
            throw ValidationException::withMessages(['advance_requested' => 'The advance cannot be more than the estimated cost.']);
        }

        $overlaps = TravelRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [TravelRequest::STATUS_PENDING, TravelRequest::STATUS_APPROVED])
            ->when($replacing, fn ($q) => $q->whereKeyNot($replacing->id))
            ->where('departure_date', '<=', $return->toDateString())
            ->where('return_date', '>=', $departure->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['departure_date' => 'You already have a pending or approved trip overlapping these dates.']);
        }

        return DB::transaction(function () use ($employee, $data, $departure, $return, $estimated, $advance, $replacing) {
            $replacing?->update(['status' => TravelRequest::STATUS_CANCELLED]);

            $request = TravelRequest::create([
                'request_number' => $this->nextNumber(),
                'employee_id' => $employee->id,
                'purpose' => $data['purpose'],
                'travel_type' => $data['travel_type'] ?? 'domestic',
                'travel_mode' => $data['travel_mode'],
                'from_location' => $data['from_location'],
                'to_location' => $data['to_location'],
                'departure_date' => $departure->toDateString(),
                'return_date' => $return->toDateString(),
                'accommodation_required' => (bool) ($data['accommodation_required'] ?? false),
                'estimated_cost' => $estimated,
                'advance_requested' => $advance,
                'status' => TravelRequest::STATUS_PENDING,
            ]);

            $this->workflow->submit($request);

            return $request->fresh();
        });
    }

    /** The employee withdraws a request that is still awaiting approval (or was sent back). */
    public function cancel(TravelRequest $request): void
    {
        if (! in_array($request->status, [TravelRequest::STATUS_PENDING, TravelRequest::STATUS_SENT_BACK], true)) {
            throw ValidationException::withMessages(['status' => 'Only a pending or sent-back request can be cancelled.']);
        }

        DB::transaction(function () use ($request) {
            $request->approvalInstance?->update(['status' => ApprovalInstance::STATUS_CANCELLED]);
            $request->update(['status' => TravelRequest::STATUS_CANCELLED]);
        });
    }

    public function recordAdvancePayment(TravelRequest $request, float $amount, string $paidOn, ?string $reference, User $paidBy): TravelRequest
    {
        if ($request->status !== TravelRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages(['status' => 'The advance can only be paid on an approved trip.']);
        }

        if ((float) $request->advance_paid > 0) {
            throw ValidationException::withMessages(['status' => 'The advance for this trip has already been paid.']);
        }

        if ($amount <= 0 || $amount > (float) $request->advance_requested) {
            throw ValidationException::withMessages(['paid_amount' => 'Enter an amount up to the approved advance of ₹'.number_format((float) $request->advance_requested, 2).'.']);
        }

        $request->update([
            'advance_paid' => round($amount, 2),
            'advance_paid_on' => $paidOn,
            'advance_reference' => $reference,
            'advance_paid_by' => $paidBy->id,
        ]);

        return $request->fresh();
    }

    /**
     * Link an expense claim to the trip it settles. Only the employee's own approved trips.
     */
    public function linkClaim(ExpenseClaim $claim, ?int $travelRequestId): void
    {
        if (! $travelRequestId) {
            $claim->update(['travel_request_id' => null]);

            return;
        }

        $request = TravelRequest::find($travelRequestId);

        if (! $request || $request->employee_id !== $claim->employee_id || $request->status !== TravelRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages(['travel_request_id' => 'Pick one of your own approved trips.']);
        }

        $claim->update(['travel_request_id' => $request->id]);
    }

    /** How much of the trip advance will be netted off this claim when it is paid. */
    public function adjustmentFor(ExpenseClaim $claim): float
    {
        if (! $claim->travel_request_id || ! $claim->travelRequest) {
            return 0.0;
        }

        return min($claim->travelRequest->outstandingAdvance(), (float) $claim->total_approved_amount);
    }

    /**
     * Close the trip once its expenses are settled. Any advance still outstanding (the trip
     * cost less than the advance) is recorded in the remarks for recovery.
     */
    public function close(TravelRequest $request, ?string $remarks): void
    {
        if ($request->status !== TravelRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages(['status' => 'Only an approved trip can be closed.']);
        }

        $outstanding = $request->outstandingAdvance();

        if ($outstanding > 0 && blank($remarks)) {
            throw ValidationException::withMessages(['closure_remarks' => '₹'.number_format($outstanding, 2).' of the advance is unadjusted — note how it was recovered.']);
        }

        $request->update([
            'status' => TravelRequest::STATUS_CLOSED,
            'closed_at' => now(),
            'closure_remarks' => $remarks,
        ]);
    }

    private function nextNumber(): string
    {
        $prefix = 'TR-'.now()->format('Ym').'-';
        $count = TravelRequest::where('request_number', 'like', $prefix.'%')->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
