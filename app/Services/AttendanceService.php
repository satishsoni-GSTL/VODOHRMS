<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AttendanceService
{
    /**
     * The industry-standard full working day, used as a fallback wherever a "complete vs
     * incomplete work hours" call has to be made for an employee with no shift assigned.
     */
    public const DEFAULT_FULL_DAY_HOURS = 8.0;

    public function activeShiftForEmployee(Employee $employee, CarbonInterface $date): ?Shift
    {
        $employeeShift = $employee->employeeShifts()
            ->where('effective_from', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date->toDateString()))
            ->orderByDesc('effective_from')
            ->first();

        return $employeeShift?->shift;
    }

    /**
     * The full-day-hours threshold to color/badge "complete" vs "incomplete" work hours
     * against — the employee's assigned shift's min_full_day_hours if one exists, otherwise
     * the standard 8-hour day.
     */
    public function minFullDayHoursFor(Employee $employee, CarbonInterface $date): float
    {
        $shift = $this->activeShiftForEmployee($employee, $date);

        return $shift ? (float) $shift->min_full_day_hours : self::DEFAULT_FULL_DAY_HOURS;
    }

    public function recalculate(Attendance $attendance): void
    {
        if (! $attendance->first_in || ! $attendance->last_out) {
            $attendance->save();

            return;
        }

        $date = $attendance->attendance_date->toDateString();
        $in = Carbon::parse("{$date} {$attendance->first_in}");
        $out = Carbon::parse("{$date} {$attendance->last_out}");

        if ($out->lessThan($in)) {
            $out->addDay();
        }

        $totalMinutes = $in->diffInMinutes($out);
        $shift = $this->activeShiftForEmployee($attendance->employee, $attendance->attendance_date);

        $breakMinutes = $shift?->break_minutes ?? 0;
        $effectiveMinutes = max($totalMinutes - $breakMinutes, 0);

        $attendance->total_hours = round($totalMinutes / 60, 2);
        $attendance->effective_hours = round($effectiveMinutes / 60, 2);

        if ($shift) {
            $shiftStart = Carbon::parse("{$date} {$shift->start_time}");
            $shiftEnd = Carbon::parse("{$date} {$shift->end_time}");
            $graceEnd = (clone $shiftStart)->addMinutes($shift->grace_minutes);

            $attendance->late_minutes = $in->greaterThan($graceEnd) ? $graceEnd->diffInMinutes($in) : 0;
            $attendance->early_going_minutes = $out->lessThan($shiftEnd) ? $out->diffInMinutes($shiftEnd) : 0;

            $minFullDayMinutes = (float) $shift->min_full_day_hours * 60;
            $minHalfDayMinutes = (float) $shift->min_half_day_hours * 60;

            // Only let the generic present/half-day state machine drive the status when it's
            // already in that state (or unset). Statuses set by a dedicated flow — Work From
            // Home, On Duty, Leave, Holiday, Weekly Off — carry their own meaning and must not
            // be silently overwritten just because punches were recorded/recalculated; late
            // marks and incomplete-hours are still computed above regardless of status.
            $statusIsGeneric = in_array($attendance->status, [
                Attendance::STATUS_PRESENT, Attendance::STATUS_HALF_DAY, Attendance::STATUS_MISSING_PUNCH,
            ], true);

            if ($statusIsGeneric && $effectiveMinutes >= $minFullDayMinutes) {
                $attendance->status = Attendance::STATUS_PRESENT;
            } elseif ($statusIsGeneric && $effectiveMinutes >= $minHalfDayMinutes) {
                $attendance->status = Attendance::STATUS_HALF_DAY;
            }
        }

        $attendance->save();
    }

    /**
     * Apply an approved regularization's corrected values to the day's attendance record,
     * preserving the pre-correction values that were captured on submission.
     *
     * An approved regularization means the day is a worked day, so it lands as Present even
     * when the register previously had it as Absent / Missing Punch. Two things are left
     * alone: a day already on approved Leave (clearing it here would drop the leave without
     * refunding the balance), and a frozen day (locked by a finalized payroll run). A
     * regularization that carries an explicit `status` in its requested values (e.g. a
     * deliberate Half Day / WFH / On Duty correction) keeps that status.
     */
    public function applyRegularization(AttendanceRegularization $regularization): void
    {
        $attendance = Attendance::firstOrNew([
            'employee_id' => $regularization->employee_id,
            'attendance_date' => $regularization->attendance_date->toDateString(),
        ]);

        if ($attendance->is_frozen) {
            return;
        }

        $requestedValues = $regularization->requested_values ?? [];
        $attendance->fill($requestedValues);
        $attendance->source = 'manual';

        $keepExistingStatus = $attendance->exists && $attendance->status === Attendance::STATUS_LEAVE;

        if (! array_key_exists('status', $requestedValues) && ! $keepExistingStatus) {
            $attendance->status = Attendance::STATUS_PRESENT;
        }

        $this->recalculate($attendance);
    }
}
