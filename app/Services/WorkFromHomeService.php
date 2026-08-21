<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkFromHomeRequest;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

class WorkFromHomeService
{
    private const DEFAULT_WEEKLY_OFF = ['saturday', 'sunday'];

    public function __construct(
        private readonly ApprovalWorkflowService $workflow,
        private readonly AttendanceService $attendanceService,
    ) {}

    public function request(Employee $employee, CarbonInterface $fromDate, CarbonInterface $toDate, string $reason): WorkFromHomeRequest
    {
        if ($toDate->lessThan($fromDate)) {
            throw ValidationException::withMessages(['to_date' => 'To date cannot be before the from date.']);
        }

        if ($this->workingDaysBetween($employee, $fromDate, $toDate) === []) {
            throw ValidationException::withMessages(['from_date' => 'The selected range has no working days (all weekly-offs/holidays).']);
        }

        $overlaps = WorkFromHomeRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [WorkFromHomeRequest::STATUS_PENDING, WorkFromHomeRequest::STATUS_APPROVED])
            ->where('from_date', '<=', $toDate->toDateString())
            ->where('to_date', '>=', $fromDate->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['from_date' => 'You already have a pending or approved Work From Home request overlapping these dates.']);
        }

        $request = WorkFromHomeRequest::create([
            'employee_id' => $employee->id,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'reason' => $reason,
            'status' => WorkFromHomeRequest::STATUS_PENDING,
        ]);

        $this->workflow->submit($request);

        return $request->fresh();
    }

    /**
     * Mark every working day in the approved range as Work From Home on the attendance
     * register. Frozen days (e.g. locked by a finalized payroll run) and days already
     * covered by approved leave are left untouched.
     */
    public function applyApprovedDays(WorkFromHomeRequest $request): void
    {
        foreach ($this->workingDaysBetween($request->employee, $request->from_date, $request->to_date) as $date) {
            $attendance = Attendance::firstOrNew([
                'employee_id' => $request->employee_id,
                'attendance_date' => $date,
            ]);

            if ($attendance->is_frozen || $attendance->status === Attendance::STATUS_LEAVE) {
                continue;
            }

            $attendance->status = Attendance::STATUS_WFH;
            $attendance->source = 'wfh';
            $attendance->save();

            $this->attendanceService->recalculate($attendance);
        }
    }

    /**
     * @return array<int, string> Y-m-d dates, excluding the employee's weekly-off days and holidays.
     */
    public function workingDaysBetween(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $weeklyOff = collect($employee->weekly_off ?: self::DEFAULT_WEEKLY_OFF)->map(fn ($d) => strtolower($d));

        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $days = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            if (in_array(strtolower($date->format('l')), $weeklyOff->all(), true)) {
                continue;
            }

            if (in_array($date->toDateString(), $holidays, true)) {
                continue;
            }

            $days[] = $date->toDateString();
        }

        return $days;
    }

    /**
     * Self-service clock-in for today, only allowed once the day is marked Work From Home
     * (i.e. a Work From Home request covering today has been approved).
     */
    public function clockIn(Employee $employee): AttendancePunch
    {
        return $this->punch($employee, 'in');
    }

    public function clockOut(Employee $employee): AttendancePunch
    {
        return $this->punch($employee, 'out');
    }

    private function punch(Employee $employee, string $type): AttendancePunch
    {
        $today = Carbon::today();

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $today->toDateString())
            ->first();

        if (! $attendance || $attendance->status !== Attendance::STATUS_WFH) {
            throw ValidationException::withMessages([
                'wfh' => 'You do not have an approved Work From Home request for today.',
            ]);
        }

        if ($attendance->is_frozen) {
            throw ValidationException::withMessages(['wfh' => "Today's attendance is locked and can no longer be punched."]);
        }

        $punchTime = now();

        $punch = $attendance->punches()->create([
            'punch_time' => $punchTime,
            'punch_type' => $type,
            'source' => 'self',
        ]);

        $time = $punchTime->format('H:i:s');

        if (! $attendance->first_in || $time < $attendance->first_in) {
            $attendance->first_in = $time;
        }

        if (! $attendance->last_out || $time > $attendance->last_out) {
            $attendance->last_out = $time;
        }

        $attendance->source = 'wfh';
        $this->attendanceService->recalculate($attendance);

        return $punch;
    }
}
