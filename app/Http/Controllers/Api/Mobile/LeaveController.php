<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Services\LeaveApplicationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends MobileController
{
    /** Leave types, this year's balances and my applications — everything the Leave tab needs. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']]);
        $employee = $this->employee($request);
        $year = (int) $request->query('year', now()->year);

        $balances = EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (LeaveType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'code' => $t->code,
                'is_paid' => $t->isPaidForPayroll(),
                'attachment_required' => (bool) $t->attachment_required,
                'min_days' => $t->min_days_per_request ? (float) $t->min_days_per_request : null,
                'max_days' => $t->max_days_per_request ? (float) $t->max_days_per_request : null,
                'balance' => [
                    'opening' => (float) ($balances->get($t->id)?->opening_balance ?? 0),
                    'credited' => (float) ($balances->get($t->id)?->credited ?? 0),
                    'used' => (float) ($balances->get($t->id)?->used ?? 0),
                    'available' => (float) ($balances->get($t->id)?->closing_balance ?? 0),
                ],
            ]);

        $applications = LeaveApplication::query()
            ->with(['leaveType', 'approvalInstance'])
            ->where('employee_id', $employee->id)
            ->whereYear('from_date', $year)
            ->orderByDesc('from_date')
            ->get()
            ->map(fn (LeaveApplication $a) => self::presentApplication($a));

        return response()->json(['year' => $year, 'types' => $types, 'applications' => $applications]);
    }

    /** Working days a range would use (weekly offs and holidays excluded), for the apply form. */
    public function preview(Request $request, LeaveApplicationService $service): JsonResponse
    {
        $data = $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'is_half_day' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'days' => $service->calculateDays(
                $this->employee($request), Carbon::parse($data['from_date']), Carbon::parse($data['to_date']), (bool) ($data['is_half_day'] ?? false),
            ),
        ]);
    }

    public function store(Request $request, LeaveApplicationService $service): JsonResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'half_day_session' => ['nullable', Rule::in(['first_half', 'second_half'])],
            'reason' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,heic,webp'],
        ]);

        $isHalfDay = (bool) ($data['is_half_day'] ?? false);

        $application = $service->apply(
            $this->employee($request),
            LeaveType::findOrFail($data['leave_type_id']),
            Carbon::parse($data['from_date']),
            Carbon::parse($isHalfDay ? $data['from_date'] : $data['to_date']),
            $isHalfDay,
            $isHalfDay ? ($data['half_day_session'] ?? 'first_half') : null,
            $data['reason'] ?? null,
            $this->storeUpload($request->file('attachment'), 'leave-attachments'),
        );

        return response()->json([
            'ok' => true,
            'message' => 'Leave applied and sent for approval.',
            'application' => self::presentApplication($application->load(['leaveType', 'approvalInstance'])),
        ], 201);
    }

    /** @return array<string, mixed> */
    public static function presentApplication(LeaveApplication $a): array
    {
        return [
            'id' => $a->id,
            'leave_type_id' => $a->leave_type_id,
            'leave_type' => $a->leaveType?->name,
            'from_date' => $a->from_date?->toDateString(),
            'to_date' => $a->to_date?->toDateString(),
            'days' => (float) $a->days,
            'is_half_day' => (bool) $a->is_half_day,
            'half_day_session' => $a->half_day_session,
            'reason' => $a->reason,
            'status' => $a->status,
            'status_label' => self::statusLabel($a->status),
            'remarks' => self::latestRemarks($a->approvalInstance),
            'can_reapply' => in_array($a->status, [LeaveApplication::STATUS_REJECTED, LeaveApplication::STATUS_SENT_BACK], true),
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
