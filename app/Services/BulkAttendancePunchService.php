<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Admin back-fill: mark an employee's absent working days as present by recording an
 * in and out punch for each. Only days that are a working day (not a weekly off or
 * holiday) and either have no attendance row or are marked Absent are touched — leave,
 * WFH, on-duty, half-day, missing-punch and payroll-frozen days are never overwritten.
 */
class BulkAttendancePunchService
{
    public function __construct(
        private readonly WorkingDayService $workingDays,
        private readonly AttendanceService $attendance,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @return array<int, string> Y-m-d absent working days between $from and $to inclusive.
     */
    public function absentDays(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $existing = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $a) => $a->attendance_date->toDateString());

        return array_values(array_filter(
            $this->workingDays->between($employee, $from, $to),
            function (string $date) use ($existing) {
                $row = $existing->get($date);

                return $row === null || ($row->status === Attendance::STATUS_ABSENT && ! $row->is_frozen);
            },
        ));
    }

    /**
     * Record punches on the given days (re-checked against absentDays, so a stale
     * selection can't overwrite a day that changed meanwhile). Returns days marked.
     *
     * @param  array<int, string>  $dates  Y-m-d
     * @return array<int, string>
     */
    public function markPresent(Employee $employee, array $dates, string $inTime, string $outTime, string $remarks, User $by): array
    {
        if ($dates === []) {
            return [];
        }

        sort($dates);
        $allowed = $this->absentDays($employee, Carbon::parse($dates[0]), Carbon::parse(end($dates)));
        $dates = array_values(array_intersect($dates, $allowed));

        $inTime = Carbon::parse($inTime)->format('H:i:s');
        $outTime = Carbon::parse($outTime)->format('H:i:s');

        DB::transaction(function () use ($employee, $dates, $inTime, $outTime, $remarks, $by) {
            foreach ($dates as $date) {
                $attendance = Attendance::firstOrNew(['employee_id' => $employee->id, 'attendance_date' => $date]);
                $old = $attendance->exists ? $attendance->only(['first_in', 'last_out', 'status']) : [];

                $attendance->fill([
                    'first_in' => $inTime,
                    'last_out' => $outTime,
                    'status' => Attendance::STATUS_PRESENT,
                    'source' => 'manual',
                    'remarks' => $remarks,
                ]);
                $attendance->save();

                $attendance->punches()->createMany([
                    ['punch_time' => "{$date} {$inTime}", 'punch_type' => 'in', 'source' => 'manual'],
                    ['punch_time' => "{$date} {$outTime}", 'punch_type' => 'out', 'source' => 'manual'],
                ]);

                $this->attendance->recalculate($attendance);

                $this->auditLog->log(
                    'bulk_punch',
                    $attendance,
                    $old,
                    $attendance->only(['first_in', 'last_out', 'status']),
                    reason: "{$remarks} (by {$by->name})",
                    module: 'attendance',
                );
            }
        });

        return $dates;
    }
}
