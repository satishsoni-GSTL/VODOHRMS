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
     * Inline hex bg/text colors per status code, shared by every screen/export that renders
     * this service's cells (Monthly Attendance View, Attendance Register, and their matching
     * Excel exports) so the palette stays visually consistent across all of them.
     */
    public const CODE_COLORS = [
        self::CODE_PRESENT => ['bg' => '#C6EFCE', 'text' => '#006100'],
        self::CODE_HALF_DAY => ['bg' => '#FFEB9C', 'text' => '#9C6500'],
        self::CODE_WFH => ['bg' => '#DDEBF7', 'text' => '#1F4E78'],
        self::CODE_ON_DUTY => ['bg' => '#DDEBF7', 'text' => '#1F4E78'],
        self::CODE_LEAVE => ['bg' => '#E4DFEC', 'text' => '#60497A'],
        self::CODE_HOLIDAY => ['bg' => '#D9D9D9', 'text' => '#404040'],
        self::CODE_WEEKLY_OFF => ['bg' => '#F2F2F2', 'text' => '#808080'],
        self::CODE_ABSENT => ['bg' => '#FFC7CE', 'text' => '#9C0006'],
        self::CODE_MISSING_PUNCH => ['bg' => '#FCE4D6', 'text' => '#833C00'],
    ];

    /**
     * Build one cell per calendar day for the given employee/month: a short status
     * code plus a display label (code, and in/out times when punches exist).
     * Priority per day: an actual attendance record (from punches) wins if present;
     * otherwise holiday, then approved leave, then weekly off, then absent/blank.
     * Also carries the raw first_in/last_out/hours (null when there's no punch data)
     * for consumers that render them directly instead of just the code/label, e.g.
     * the Attendance Register matrix.
     *
     * @return array<string, array{code: string, label: string, first_in: ?string, last_out: ?string, hours: ?float}> keyed by Y-m-d
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
                array_key_exists($dateString, $leaveDates) => $this->plainCell(self::CODE_LEAVE),
                in_array($dateString, $holidays, true) => $this->plainCell(self::CODE_HOLIDAY),
                in_array(strtolower($date->format('l')), $weeklyOff->all(), true) => $this->plainCell(self::CODE_WEEKLY_OFF),
                $date->greaterThan($today) => $this->plainCell(''),
                default => $this->plainCell(self::CODE_ABSENT),
            };
        }

        return $days;
    }

    /**
     * @return array{code: string, label: string, first_in: ?string, last_out: ?string, hours: ?float}
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

        $hours = $attendance->effective_hours ?? $attendance->total_hours;

        if ($attendance->hasDistinctPunches()) {
            return [
                'code' => $statusCode,
                'label' => "{$statusCode} {$attendance->first_in}-{$attendance->last_out}",
                'first_in' => $attendance->first_in,
                'last_out' => $attendance->last_out,
                'hours' => $hours !== null ? (float) $hours : null,
            ];
        }

        // A one-sided punch (only an in, or only an out) still counts as a present day —
        // the lone punch time is surfaced so it's clear the other side is missing.
        if ($attendance->first_in || $attendance->last_out) {
            $punch = $attendance->first_in ?: $attendance->last_out;

            return [
                'code' => $statusCode,
                'label' => "{$statusCode} {$punch}",
                'first_in' => $attendance->first_in ?: null,
                'last_out' => $attendance->first_in ? null : $attendance->last_out,
                'hours' => $hours !== null ? (float) $hours : null,
            ];
        }

        return $this->plainCell($statusCode);
    }

    /**
     * @return array{code: string, label: string, first_in: null, last_out: null, hours: null}
     */
    private function plainCell(string $code): array
    {
        return ['code' => $code, 'label' => $code, 'first_in' => null, 'last_out' => null, 'hours' => null];
    }
}
