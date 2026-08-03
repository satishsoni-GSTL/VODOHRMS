<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\BiometricDevice;
use App\Models\DevicePunchLog;
use App\Models\Employee;
use Carbon\Carbon;

class BiometricPunchService
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * Ingest one raw punch from a device push: dedupe, resolve the employee, and
     * (if matched) record it against the day's attendance. Returns one of
     * 'matched', 'unmatched', 'duplicate'.
     */
    public function ingest(BiometricDevice $device, array $punch): string
    {
        $punchTime = Carbon::parse($punch['punch_time']);

        $log = DevicePunchLog::firstOrNew([
            'biometric_device_id' => $device->id,
            'device_user_id' => $punch['device_user_id'],
            'punch_time' => $punchTime,
        ]);

        if ($log->exists) {
            return 'duplicate';
        }

        $log->fill([
            'punch_type' => $punch['punch_type'] ?? null,
            'raw_payload' => $punch,
            'status' => DevicePunchLog::STATUS_PENDING,
        ]);

        return $this->resolveAndSave($log, $punchTime);
    }

    /**
     * Re-attempt matching for a previously unmatched log (e.g. after HR fills in
     * the employee's biometric_enroll_id). Returns true if it now matched.
     */
    public function rematch(DevicePunchLog $log): bool
    {
        return $this->resolveAndSave($log, $log->punch_time) === 'matched';
    }

    private function resolveAndSave(DevicePunchLog $log, Carbon $punchTime): string
    {
        $employee = Employee::where('biometric_enroll_id', $log->device_user_id)->first();

        if (! $employee) {
            $log->status = DevicePunchLog::STATUS_UNMATCHED;
            $log->save();

            return 'unmatched';
        }

        $attendancePunch = $this->recordPunch($employee, $punchTime, $log->punch_type);

        $log->employee_id = $employee->id;
        $log->status = DevicePunchLog::STATUS_MATCHED;
        $log->attendance_punch_id = $attendancePunch->id;
        $log->save();

        return 'matched';
    }

    private function recordPunch(Employee $employee, Carbon $punchTime, ?string $punchType): AttendancePunch
    {
        $attendance = Attendance::firstOrCreate(
            ['employee_id' => $employee->id, 'attendance_date' => $punchTime->toDateString()],
            ['status' => Attendance::STATUS_PRESENT, 'source' => 'biometric'],
        );

        $attendancePunch = $attendance->punches()->create([
            'punch_time' => $punchTime,
            'punch_type' => $punchType ?? 'in',
            'source' => 'biometric',
        ]);

        if (! $attendance->is_frozen) {
            $time = $punchTime->format('H:i:s');

            if (! $attendance->first_in || $time < $attendance->first_in) {
                $attendance->first_in = $time;
            }

            if (! $attendance->last_out || $time > $attendance->last_out) {
                $attendance->last_out = $time;
            }

            $attendance->source = 'biometric';
            $this->attendanceService->recalculate($attendance);
        }

        return $attendancePunch;
    }
}
