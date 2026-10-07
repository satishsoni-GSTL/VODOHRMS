<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\AttendanceRegularization;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\LeaveApplication;
use App\Models\WorkFromHomeRequest;
use App\Services\AttendanceMonthlySummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * "My Team" for managers: their direct and indirect reports (the same reporting-line scope
 * the web panel uses for managers) — who's in today, each member's attendance month, team
 * leave, and the team's WFH / regularization / expense requests. Read-only; acting on
 * requests is the Approvals tab.
 */
class TeamController extends MobileController
{
    private const STATUS_LABELS = [
        'P' => 'Present', 'HD' => 'Half Day', 'WFH' => 'Work From Home', 'OD' => 'On Duty', 'L' => 'On Leave',
        'H' => 'Holiday', 'WO' => 'Weekly Off', 'A' => 'Absent', 'MP' => 'Missing Punch', '' => 'Not yet marked',
    ];

    /** Team roster with each member's status for a day (default today) and a summary. */
    public function index(Request $request, AttendanceMonthlySummaryService $summary): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $date = Carbon::parse($request->query('date', now()->toDateString()));
        $manager = $this->employee($request);

        $members = $this->members($request)->map(function (Employee $e) use ($summary, $date, $manager) {
            $day = $summary->buildForEmployee($e, $date, $date)[$date->toDateString()] ?? ['code' => '', 'first_in' => null, 'last_out' => null, 'hours' => null];
            // A day that has started but has no punch yet isn't "absent" until the day is over.
            $code = $day['code'] === 'A' && $date->isToday() ? '' : $day['code'];

            return [
                'id' => $e->id,
                'name' => ProfileController::cleanName($e->full_name),
                'employee_code' => $e->employee_code,
                'designation' => $e->designation?->name,
                'department' => $e->department?->name,
                'is_direct' => $e->reporting_manager_id === $manager->id,
                'status_code' => $code,
                'status_label' => self::STATUS_LABELS[$code] ?? $code,
                'first_in' => $day['first_in'],
                'last_out' => $day['last_out'] !== $day['first_in'] ? $day['last_out'] : null,
                'hours' => $day['hours'],
            ];
        })->values();

        $counts = $members->countBy('status_code');

        return response()->json([
            'date' => $date->toDateString(),
            'summary' => [
                'total' => $members->count(),
                'present' => $counts->get('P', 0) + $counts->get('HD', 0) + $counts->get('OD', 0) + $counts->get('MP', 0),
                'wfh' => $counts->get('WFH', 0),
                'on_leave' => $counts->get('L', 0),
                'absent' => $counts->get('A', 0),
                'not_marked' => $counts->get('', 0),
                'off' => $counts->get('WO', 0) + $counts->get('H', 0),
            ],
            'members' => $members,
        ]);
    }

    /** One team member's attendance month (same view as "My Attendance"). */
    public function memberAttendance(Request $request, Employee $employee, AttendanceMonthlySummaryService $summary): JsonResponse
    {
        $this->authorizeMember($request, $employee);
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $start = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $days = collect($summary->buildForEmployee($employee, $start, $start->copy()->endOfMonth()))
            ->map(fn (array $row, string $date) => [
                'date' => $date,
                'code' => $row['code'],
                'label' => self::STATUS_LABELS[$row['code']] ?? '',
                'first_in' => $row['first_in'],
                'last_out' => $row['last_out'] !== $row['first_in'] ? $row['last_out'] : null,
                'hours' => $row['hours'],
            ])->values();

        $counts = $days->countBy('code');
        $hours = $days->pluck('hours')->filter(fn ($h) => $h !== null);

        return response()->json([
            'employee' => ['id' => $employee->id, 'name' => ProfileController::cleanName($employee->full_name), 'employee_code' => $employee->employee_code],
            'month' => $start->format('Y-m'),
            'days' => $days,
            'totals' => [
                'present' => $counts->get('P', 0), 'half_day' => $counts->get('HD', 0), 'wfh' => $counts->get('WFH', 0),
                'on_duty' => $counts->get('OD', 0), 'leave' => $counts->get('L', 0), 'holiday' => $counts->get('H', 0),
                'weekly_off' => $counts->get('WO', 0), 'absent' => $counts->get('A', 0), 'missing_punch' => $counts->get('MP', 0),
                'hours' => round($hours->sum(), 2), 'avg_hours' => $hours->count() ? round($hours->avg(), 2) : 0,
            ],
        ]);
    }

    /** Team leave overlapping a month — who is away when. */
    public function leave(Request $request): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $start = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $items = LeaveApplication::query()
            ->with(['employee', 'leaveType', 'approvalInstance'])
            ->whereIn('employee_id', $this->memberIds($request))
            ->where('status', '!=', LeaveApplication::STATUS_CANCELLED)
            ->where('from_date', '<=', $end->toDateString())
            ->where('to_date', '>=', $start->toDateString())
            ->orderBy('from_date')
            ->get()
            ->map(fn (LeaveApplication $a) => LeaveController::presentApplication($a) + self::who($a->employee));

        return response()->json(['month' => $start->format('Y-m'), 'items' => $items]);
    }

    /** The team's recent WFH, regularization or expense requests. */
    public function requests(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['wfh', 'regularization', 'expense'])]]);
        $ids = $this->memberIds($request);

        $items = match ($data['type']) {
            'wfh' => WorkFromHomeRequest::query()->with(['employee', 'approvalInstance'])->whereIn('employee_id', $ids)
                ->latest('from_date')->limit(100)->get()
                ->map(fn (WorkFromHomeRequest $r) => self::who($r->employee) + [
                    'id' => $r->id,
                    'title' => 'Work From Home · '.$r->total_days.' day(s)',
                    'subtitle' => $r->from_date?->format('d M Y').($r->to_date && ! $r->to_date->equalTo($r->from_date) ? ' → '.$r->to_date->format('d M Y') : ''),
                    'detail' => $r->reason,
                    'status' => $r->status,
                    'status_label' => WorkFromHomeRequest::STATUSES[$r->status] ?? self::statusLabel($r->status),
                    'remarks' => self::latestRemarks($r->approvalInstance),
                ]),
            'regularization' => AttendanceRegularization::query()->with(['employee', 'approvalInstance'])->whereIn('employee_id', $ids)
                ->latest('attendance_date')->limit(100)->get()
                ->map(fn (AttendanceRegularization $r) => self::who($r->employee) + [
                    'id' => $r->id,
                    'title' => AttendanceRegularization::TYPES[$r->request_type] ?? 'Regularization',
                    'subtitle' => $r->attendance_date?->format('d M Y')
                        .(isset($r->requested_values['first_in']) ? ' · In '.substr($r->requested_values['first_in'], 0, 5) : '')
                        .(isset($r->requested_values['last_out']) ? ' · Out '.substr($r->requested_values['last_out'], 0, 5) : ''),
                    'detail' => $r->reason,
                    'status' => $r->status,
                    'status_label' => self::statusLabel($r->status),
                    'remarks' => self::latestRemarks($r->approvalInstance),
                ]),
            'expense' => ExpenseClaim::query()->with(['employee', 'approvalInstance'])->withCount('lines')->whereIn('employee_id', $ids)
                ->latest('claim_date')->limit(100)->get()
                ->map(fn (ExpenseClaim $c) => self::who($c->employee) + [
                    'id' => $c->id,
                    'title' => "{$c->claim_number} · ₹".number_format((float) $c->total_requested_amount, 2),
                    'subtitle' => $c->claim_date?->format('d M Y')." · {$c->lines_count} item(s)",
                    'detail' => $c->project_client,
                    'status' => $c->status,
                    'status_label' => ExpenseClaim::STATUSES[$c->status] ?? self::statusLabel($c->status),
                    'remarks' => self::latestRemarks($c->approvalInstance),
                ]),
        };

        return response()->json(['type' => $data['type'], 'items' => $items->values()]);
    }

    /** @return Collection<int, Employee> */
    private function members(Request $request): Collection
    {
        return Employee::query()
            ->with(['designation', 'department'])
            ->whereIn('id', $this->memberIds($request))
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD])
            ->orderBy('first_name')
            ->get();
    }

    /** @return array<int, int> */
    private function memberIds(Request $request): array
    {
        $ids = $this->employee($request)->allSubordinateIds();
        abort_if($ids === [], 403, 'You have no team members reporting to you.');

        return $ids;
    }

    private function authorizeMember(Request $request, Employee $employee): void
    {
        abort_unless(in_array($employee->id, $this->employee($request)->allSubordinateIds(), true), 404);
    }

    /** @return array{employee: array{id: int, name: string, employee_code: ?string}} */
    private static function who(?Employee $e): array
    {
        return ['employee' => [
            'id' => $e?->id,
            'name' => ProfileController::cleanName($e?->full_name),
            'employee_code' => $e?->employee_code,
        ]];
    }
}
