<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Self clock-in/out from the mobile app, geo-tagged. Which kind of punch is allowed today:
 *
 *  - wfh    — the day is an approved Work From Home day. Location is recorded if the app
 *             sends it, but not checked (the employee is at home).
 *  - field  — field-force employee (workforce_type = field). Location is mandatory and
 *             recorded; they may punch from anywhere since they work in the market.
 *  - office — the employee's Location has a geofence. Location is mandatory and the punch is
 *             refused when it falls outside the radius.
 *
 * Otherwise app punching is not allowed (office staff without a geofence use the biometric
 * device).
 */
class MobilePunchService
{
    public const MODE_WFH = 'wfh';

    public const MODE_FIELD = 'field';

    public const MODE_OFFICE = 'office';

    public function __construct(
        private readonly WorkFromHomeService $wfh,
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * @return array{mode: string, requires_location: bool, geofence: array{location: string, latitude: float, longitude: float, radius_m: int}|null}|null
     */
    public function modeFor(Employee $employee): ?array
    {
        $attendance = $this->todaysAttendance($employee);

        if ($attendance?->is_frozen || $attendance?->status === Attendance::STATUS_LEAVE) {
            return null;
        }

        if ($attendance?->status === Attendance::STATUS_WFH) {
            return ['mode' => self::MODE_WFH, 'requires_location' => false, 'geofence' => null];
        }

        if ($employee->workforce_type === 'field') {
            return ['mode' => self::MODE_FIELD, 'requires_location' => true, 'geofence' => null];
        }

        $location = $employee->location;

        if ($location?->hasGeofence()) {
            return [
                'mode' => self::MODE_OFFICE,
                'requires_location' => true,
                'geofence' => [
                    'location' => $location->name,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'radius_m' => $location->geofence_radius_m,
                ],
            ];
        }

        return null;
    }

    /**
     * @param  array{latitude?: float|null, longitude?: float|null, accuracy_m?: float|null}  $geo
     */
    public function punch(Employee $employee, string $type, array $geo = []): AttendancePunch
    {
        $mode = $this->modeFor($employee);

        if ($mode === null) {
            $attendance = $this->todaysAttendance($employee);

            throw ValidationException::withMessages(['wfh' => match (true) {
                (bool) $attendance?->is_frozen => "Today's attendance is locked and can no longer be punched.",
                $attendance?->status === Attendance::STATUS_LEAVE => 'You are on leave today.',
                default => 'You do not have an approved Work From Home request for today.',
            }]);
        }

        $geo = array_filter($geo, fn ($v) => $v !== null);

        if ($mode['mode'] === self::MODE_WFH) {
            return $type === 'in' ? $this->wfh->clockIn($employee, $geo) : $this->wfh->clockOut($employee, $geo);
        }

        if (! isset($geo['latitude'], $geo['longitude'])) {
            throw ValidationException::withMessages([
                'latitude' => 'Your location is required to punch attendance. Turn on location (GPS) and try again.',
            ]);
        }

        $fence = [];

        if ($mode['mode'] === self::MODE_OFFICE) {
            /** @var Location $location */
            $location = $employee->location;
            $distance = $location->distanceTo($geo['latitude'], $geo['longitude']);

            if ($distance > $location->geofence_radius_m) {
                throw ValidationException::withMessages([
                    'latitude' => sprintf(
                        'You are %s from %s. Attendance can only be punched within %d m of the office.',
                        $distance >= 1000 ? round($distance / 1000, 1).' km' : "{$distance} m",
                        $location->name,
                        $location->geofence_radius_m,
                    ),
                ]);
            }

            $fence = ['location_id' => $location->id, 'distance_m' => $distance, 'within_geofence' => true];
        }

        return $this->record($employee, $type, $geo + $fence);
    }

    private function record(Employee $employee, string $type, array $geoFields): AttendancePunch
    {
        $now = now();

        $attendance = Attendance::firstOrCreate(
            ['employee_id' => $employee->id, 'attendance_date' => $now->toDateString()],
            ['status' => Attendance::STATUS_PRESENT, 'source' => 'mobile'],
        );

        $punch = $attendance->punches()->create([
            'punch_time' => $now,
            'punch_type' => $type,
            'source' => 'mobile',
        ] + $geoFields);

        $time = $now->format('H:i:s');

        if (! $attendance->first_in || $time < $attendance->first_in) {
            $attendance->first_in = $time;
        }

        if (! $attendance->last_out || $time > $attendance->last_out) {
            $attendance->last_out = $time;
        }

        // An absent / missing-punch day becomes a worked day once the employee punches in.
        if (in_array($attendance->status, [Attendance::STATUS_ABSENT, Attendance::STATUS_MISSING_PUNCH], true)) {
            $attendance->status = Attendance::STATUS_PRESENT;
        }

        $attendance->source = 'mobile';
        $this->attendanceService->recalculate($attendance);

        return $punch;
    }

    private function todaysAttendance(Employee $employee): ?Attendance
    {
        return Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', Carbon::today()->toDateString())
            ->first();
    }
}
