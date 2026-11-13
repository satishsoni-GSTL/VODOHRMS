<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\TravelRequest;
use App\Services\TravelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TravelController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $items = TravelRequest::query()
            ->with('approvalInstance')
            ->where('employee_id', $this->employee($request)->id)
            ->orderByDesc('departure_date')
            ->limit(100)
            ->get()
            ->map(fn (TravelRequest $r) => [
                'id' => $r->id,
                'request_number' => $r->request_number,
                'purpose' => $r->purpose,
                'from_location' => $r->from_location,
                'to_location' => $r->to_location,
                'departure_date' => $r->departure_date?->toDateString(),
                'return_date' => $r->return_date?->toDateString(),
                'travel_mode' => $r->travel_mode,
                'travel_mode_label' => TravelRequest::MODES[$r->travel_mode] ?? $r->travel_mode,
                'estimated_cost' => (float) $r->estimated_cost,
                'advance_requested' => (float) $r->advance_requested,
                'advance_paid' => (float) $r->advance_paid,
                'status' => $r->status,
                'status_label' => TravelRequest::STATUSES[$r->status] ?? self::statusLabel($r->status),
                'remarks' => self::latestRemarks($r->approvalInstance),
                'can_cancel' => in_array($r->status, [TravelRequest::STATUS_PENDING, TravelRequest::STATUS_SENT_BACK], true),
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'modes' => collect(TravelRequest::MODES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'items' => $items,
        ]);
    }

    public function store(Request $request, TravelService $service): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'string', 'max:2000'],
            'travel_type' => ['nullable', Rule::in(array_keys(TravelRequest::TYPES))],
            'travel_mode' => ['required', Rule::in(array_keys(TravelRequest::MODES))],
            'from_location' => ['required', 'string', 'max:120'],
            'to_location' => ['required', 'string', 'max:120'],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:departure_date'],
            'accommodation_required' => ['nullable', 'boolean'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'advance_requested' => ['nullable', 'numeric', 'min:0'],
        ]);

        $trip = $service->request($this->employee($request), $data);

        return response()->json(['ok' => true, 'message' => 'Travel request sent for approval.', 'id' => $trip->id, 'request_number' => $trip->request_number], 201);
    }

    public function cancel(Request $request, TravelRequest $travel, TravelService $service): JsonResponse
    {
        abort_unless($travel->employee_id === $this->employee($request)->id, 404);

        $service->cancel($travel);

        return response()->json(['ok' => true, 'message' => 'Travel request cancelled.']);
    }
}
