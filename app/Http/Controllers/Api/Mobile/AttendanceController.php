<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\AttendanceRegularization;
use App\Services\AttendanceMonthlySummaryService;
use App\Services\AttendanceRegularizationService;
use App\Services\WorkFromHomeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends MobileController
{
    private const CODE_LABELS = [
        AttendanceMonthlySummaryService::CODE_PRESENT => 'Present',
        AttendanceMonthlySummaryService::CODE_HALF_DAY => 'Half Day',
        AttendanceMonthlySummaryService::CODE_WFH => 'Work From Home',
        AttendanceMonthlySummaryService::CODE_ON_DUTY => 'On Duty',
        AttendanceMonthlySummaryService::CODE_LEAVE => 'Leave',
        AttendanceMonthlySummaryService::CODE_HOLIDAY => 'Holiday',
        AttendanceMonthlySummaryService::CODE_WEEKLY_OFF => 'Weekly Off',
        AttendanceMonthlySummaryService::CODE_ABSENT => 'Absent',
        AttendanceMonthlySummaryService::CODE_MISSING_PUNCH => 'Missing Punch',
    ];

    /** Same day-by-day view as the web "My Attendance" page. */
    public function month(Request $request, AttendanceMonthlySummaryService $summary): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $start = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $rows = $summary->buildForEmployee($this->employee($request), $start, $start->copy()->endOfMonth());

        $days = collect($rows)->map(fn (array $row, string $date) => [
            'date' => $date,
            'code' => $row['code'],
            'label' => self::CODE_LABELS[$row['code']] ?? '',
            'first_in' => $row['first_in'],
            'last_out' => $row['last_out'] !== $row['first_in'] ? $row['last_out'] : null,
            'hours' => $row['hours'],
        ])->values();

        $counts = $days->countBy('code');
        $hours = $days->pluck('hours')->filter(fn ($h) => $h !== null);

        return response()->json([
            'month' => $start->format('Y-m'),
            'days' => $days,
            'totals' => [
                'present' => $counts->get('P', 0),
                'half_day' => $counts->get('HD', 0),
                'wfh' => $counts->get('WFH', 0),
                'on_duty' => $counts->get('OD', 0),
                'leave' => $counts->get('L', 0),
                'holiday' => $counts->get('H', 0),
                'weekly_off' => $counts->get('WO', 0),
                'absent' => $counts->get('A', 0),
                'missing_punch' => $counts->get('MP', 0),
                'hours' => round($hours->sum(), 2),
                'avg_hours' => $hours->count() ? round($hours->avg(), 2) : 0,
            ],
        ]);
    }

    /** Self punch — only on an approved Work From Home day (enforced by the service). */
    public function clock(Request $request, WorkFromHomeService $wfh): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['in', 'out'])]]);
        $employee = $this->employee($request);

        $punch = $data['type'] === 'in' ? $wfh->clockIn($employee) : $wfh->clockOut($employee);

        return response()->json([
            'ok' => true,
            'message' => $data['type'] === 'in' ? 'Clocked in.' : 'Clocked out.',
            'time' => $punch->punch_time?->format('H:i'),
        ]);
    }

    public function regularizations(Request $request): JsonResponse
    {
        $items = AttendanceRegularization::query()
            ->with('approvalInstance')
            ->where('employee_id', $this->employee($request)->id)
            ->latest('attendance_date')
            ->limit(100)
            ->get()
            ->map(fn (AttendanceRegularization $r) => [
                'id' => $r->id,
                'attendance_date' => $r->attendance_date?->toDateString(),
                'request_type' => $r->request_type,
                'request_type_label' => AttendanceRegularization::TYPES[$r->request_type] ?? $r->request_type,
                'first_in' => $r->requested_values['first_in'] ?? null,
                'last_out' => $r->requested_values['last_out'] ?? null,
                'reason' => $r->reason,
                'status' => $r->status,
                'status_label' => self::statusLabel($r->status),
                'remarks' => self::latestRemarks($r->approvalInstance),
                'can_reapply' => in_array($r->status, ['rejected', 'sent_back'], true),
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'types' => collect(AttendanceRegularization::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'items' => $items,
        ]);
    }

    public function requestRegularization(Request $request, AttendanceRegularizationService $service): JsonResponse
    {
        $data = $request->validate([
            'attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'request_type' => ['required', Rule::in(array_keys(AttendanceRegularization::TYPES))],
            'first_in' => ['nullable', 'date_format:H:i'],
            'last_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,heic,webp'],
        ]);

        $requested = array_filter([
            'first_in' => isset($data['first_in']) ? $data['first_in'].':00' : null,
            'last_out' => isset($data['last_out']) ? $data['last_out'].':00' : null,
        ]);

        $regularization = $service->request(
            $this->employee($request),
            Carbon::parse($data['attendance_date']),
            $data['request_type'],
            $requested,
            $data['reason'],
            $this->storeUpload($request->file('attachment'), 'regularization-attachments'),
        );

        return response()->json(['ok' => true, 'message' => 'Regularization submitted for approval.', 'id' => $regularization->id], 201);
    }
}
