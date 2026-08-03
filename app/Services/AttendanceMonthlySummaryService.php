<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

class AttendanceMonthlySummaryService
{
    public const CODE_PRESENT = 'P';

    public const CODE_HALF_DAY = 'HD';

    public const CODE_WFH = 'WFH';

    public const CODE_ON_DUTY = 'OD';

    public const CODE_LEAVE = 'L';

    public const CODE_HOLIDAY = 'H';

    public const CODE_WEEKLY_OFF = 'WO';

    public const CODE_ABSENT = 'A';

    public const CODE_MISSING_PUNCH = 'MP';

    private const DEFAULT_WEEKLY_OFF = ['saturday', 'sunday'];

    /**
     * Build one cell per calendar day for the given employee/month: a short status
     * code plus a display label (code, and in/out times when punches exist).
     * Priority per day: an actual attendance record (from punches) wins if present;
     * otherwise holiday, then approved leave, then weekly off, then absent/blank.
     *
     * @return array<string, array{code: string, label: string}> keyed by Y-m-d
     */
    public function buildForEmployee(Employee $employee, CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        $weeklyOff = collect($employee->weekly_off ?: self::DEFAULT_WEEKLY_OFF)->map(fn ($d) => strtolower($d));

        $holidays = Holiday::query()
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $leaveDates = [];
        LeaveApplication::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveApplication::STATUS_APPROVED)
            ->where('from_date', '<=', $monthEnd->toDateString())
            ->where('to_date', '>=', $monthStart->toDateString())
            ->get()
            ->each(function (LeaveApplication $application) use (&$leaveDates) {
                foreach (CarbonPeriod::create($application->from_date, $application->to_date) as $date) {
                    $leaveDates[$date->toDateString()] = true;
                }
            });

        $attendanceByDate = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $a) => $a->attendance_date->toDateString());

        $today = now()->startOfDay();
        $days = [];

        foreach (CarbonPeriod::create($monthStart, $monthEnd) as $date) {
            $dateString = $date->toDateString();
            $attendance = $attendanceByDate->get($dateString);

            $days[$dateString] = match (true) {
                $attendance !== null => $this->cellForAttendance($attendance),
                array_key_exists($dateString, $leaveDates) => ['code' => self::CODE_LEAVE, 'label' => self::CODE_LEAVE],
                in_array($dateString, $holidays, true) => ['code' => self::CODE_HOLIDAY, 'label' => self::CODE_HOLIDAY],
                in_array(strtolower($date->format('l')), $weeklyOff->all(), true) => ['code' => self::CODE_WEEKLY_OFF, 'label' => self::CODE_WEEKLY_OFF],
                $date->greaterThan($today) => ['code' => '', 'label' => ''],
                default => ['code' => self::CODE_ABSENT, 'label' => self::CODE_ABSENT],
            };
        }

        return $days;
    }

    /**
     * @return array{code: string, label: string}
     */
    private function cellForAttendance(Attendance $attendance): array
    {
        $statusCode = match ($attendance->status) {
            Attendance::STATUS_HALF_DAY => self::CODE_HALF_DAY,
            Attendance::STATUS_WFH => self::CODE_WFH,
            Attendance::STATUS_ON_DUTY => self::CODE_ON_DUTY,
            Attendance::STATUS_LEAVE => self::CODE_LEAVE,
            Attendance::STATUS_HOLIDAY => self::CODE_HOLIDAY,
            Attendance::STATUS_WEEKLY_OFF => self::CODE_WEEKLY_OFF,
            default => self::CODE_PRESENT,
        };

        if ($attendance->first_in && $attendance->last_out) {
            return ['code' => $statusCode, 'label' => "{$statusCode} {$attendance->first_in}-{$attendance->last_out}"];
        }

        if ($attendance->first_in) {
            return ['code' => self::CODE_MISSING_PUNCH, 'label' => "IN {$attendance->first_in}"];
        }

        if ($attendance->last_out) {
            return ['code' => self::CODE_MISSING_PUNCH, 'label' => "OUT {$attendance->last_out}"];
        }

        return ['code' => $statusCode, 'label' => $statusCode];
    }
}
