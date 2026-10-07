<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\WorkFromHomeRequest;
use App\Services\WorkFromHomeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkFromHomeController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $items = WorkFromHomeRequest::query()
            ->with('approvalInstance')
            ->where('employee_id', $this->employee($request)->id)
            ->orderByDesc('from_date')
            ->limit(100)
            ->get()
            ->map(fn (WorkFromHomeRequest $r) => [
                'id' => $r->id,
                'from_date' => $r->from_date?->toDateString(),
                'to_date' => $r->to_date?->toDateString(),
                'working_days' => $r->total_days,
                'reason' => $r->reason,
                'status' => $r->status,
                'status_label' => WorkFromHomeRequest::STATUSES[$r->status] ?? self::statusLabel($r->status),
                'remarks' => self::latestRemarks($r->approvalInstance),
                'can_reapply' => in_array($r->status, [WorkFromHomeRequest::STATUS_REJECTED, WorkFromHomeRequest::STATUS_SENT_BACK], true),
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return response()->json(['items' => $items]);
    }

    public function store(Request $request, WorkFromHomeService $service): JsonResponse
    {
        $data = $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $wfh = $service->request($this->employee($request), Carbon::parse($data['from_date']), Carbon::parse($data['to_date']), $data['reason']);

        return response()->json(['ok' => true, 'message' => 'Work From Home request sent for approval.', 'id' => $wfh->id], 201);
    }
}
