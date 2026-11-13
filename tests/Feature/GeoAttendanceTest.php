<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Geo-tagged mobile punching: WFH (location recorded, not checked), field force (location
 * mandatory, anywhere) and office staff (location mandatory, must be inside the geofence).
 */
class GeoAttendanceTest extends TestCase
{
    use RefreshDatabase;

    // Office centre and a point ~1.1 km north of it.
    private const OFFICE = [19.0760, 72.8777];

    private const FAR = [19.0860, 72.8777];

    private Location $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        Carbon::setTestNow(Carbon::parse('2026-10-07 09:45')); // a Wednesday

        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Mumbai', 'code' => 'MUM', 'is_active' => true]);
        $this->office = Location::create([
            'branch_id' => $branch->id, 'name' => 'Andheri Office', 'country' => 'India', 'is_active' => true,
            'latitude' => self::OFFICE[0], 'longitude' => self::OFFICE[1], 'geofence_radius_m' => 200,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tokenForNew(string $code, array $attributes = []): string
    {
        $company = Company::where('code', 'HO')->first();

        $employee = Employee::create($attributes + [
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => '2025-01-01',
            'status' => Employee::STATUS_ACTIVE,
            'weekly_off' => ['saturday', 'sunday'],
        ]);

        User::create([
            'employee_id' => $employee->id,
            'employee_code' => $code,
            'name' => $code,
            'email' => $employee->official_email,
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ])->assignRole('Employee');

        return $this->postJson('/api/mobile/login', ['login' => $code, 'password' => 'secret123'])->assertOk()->json('token');
    }

    public function test_distance_calculation(): void
    {
        $this->assertSame(0, Location::distanceBetween(...self::OFFICE, ...self::OFFICE));
        $this->assertEqualsWithDelta(1112, Location::distanceBetween(...self::OFFICE, ...self::FAR), 5);
    }

    public function test_office_staff_can_punch_inside_geofence_only(): void
    {
        $token = $this->tokenForNew('OFF1', ['location_id' => $this->office->id]);

        $this->withToken($token)->getJson('/api/mobile/dashboard')
            ->assertJsonPath('today.can_clock', true)
            ->assertJsonPath('today.clock_mode', 'office')
            ->assertJsonPath('today.clock_requires_location', true)
            ->assertJsonPath('today.geofence.radius_m', 200);

        // No location sent -> refused.
        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'in'])
            ->assertStatus(422)->assertJsonValidationErrors('latitude');

        // 1.1 km away -> refused, nothing recorded.
        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'in', 'latitude' => self::FAR[0], 'longitude' => self::FAR[1]])
            ->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->assertSame(0, AttendancePunch::count());

        // ~50 m away -> accepted and geo-tagged.
        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'in', 'latitude' => 19.07645, 'longitude' => 72.8777, 'accuracy' => 12])
            ->assertOk()->assertJsonPath('time', '09:45');

        $punch = AttendancePunch::sole();
        $this->assertSame('mobile', $punch->source);
        $this->assertSame($this->office->id, $punch->location_id);
        $this->assertTrue($punch->within_geofence);
        $this->assertEqualsWithDelta(50, $punch->distance_m, 5);
        $this->assertEquals(12.0, $punch->accuracy_m);

        $attendance = Attendance::sole();
        $this->assertSame(Attendance::STATUS_PRESENT, $attendance->status);
        $this->assertSame('09:45:00', $attendance->first_in);
    }

    public function test_field_staff_punch_from_anywhere_but_must_send_location(): void
    {
        $token = $this->tokenForNew('FLD1', ['workforce_type' => 'field', 'location_id' => $this->office->id]);

        $this->withToken($token)->getJson('/api/mobile/dashboard')->assertJsonPath('today.clock_mode', 'field');

        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'in'])->assertStatus(422);

        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'in', 'latitude' => self::FAR[0], 'longitude' => self::FAR[1]])
            ->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-10-07 18:40'));
        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'out', 'latitude' => 19.1, 'longitude' => 72.9])
            ->assertOk();

        $this->assertSame(2, AttendancePunch::whereNotNull('latitude')->whereNull('location_id')->count());
        $attendance = Attendance::sole();
        $this->assertSame('09:45:00', $attendance->first_in);
        $this->assertSame('18:40:00', $attendance->last_out);
    }

    public function test_office_staff_without_geofence_cannot_punch_from_app(): void
    {
        $token = $this->tokenForNew('OFF2');

        $this->withToken($token)->getJson('/api/mobile/dashboard')
            ->assertJsonPath('today.can_clock', false)
            ->assertJsonPath('today.clock_mode', null);

        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'in', 'latitude' => self::OFFICE[0], 'longitude' => self::OFFICE[1]])
            ->assertStatus(422)->assertJsonValidationErrors('wfh');
    }

    public function test_wfh_day_records_location_without_geofence_check(): void
    {
        $token = $this->tokenForNew('WFH1', ['location_id' => $this->office->id]);
        $employee = Employee::where('employee_code', 'WFH1')->first();
        Attendance::create(['employee_id' => $employee->id, 'attendance_date' => '2026-10-07', 'status' => Attendance::STATUS_WFH]);

        $this->withToken($token)->getJson('/api/mobile/dashboard')->assertJsonPath('today.clock_mode', 'wfh');

        // Far from the office, and also works with no location at all (older app versions).
        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'in', 'latitude' => self::FAR[0], 'longitude' => self::FAR[1]])
            ->assertOk();
        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'out'])->assertOk();

        $punches = AttendancePunch::orderBy('id')->get();
        $this->assertSame(['self', 'self'], $punches->pluck('source')->all());
        $this->assertEquals(self::FAR[0], $punches[0]->latitude);
        $this->assertNull($punches[1]->latitude);
        $this->assertSame(Attendance::STATUS_WFH, Attendance::sole()->status);
    }

    public function test_cannot_punch_on_leave_day(): void
    {
        $token = $this->tokenForNew('FLD2', ['workforce_type' => 'field']);
        $employee = Employee::where('employee_code', 'FLD2')->first();
        Attendance::create(['employee_id' => $employee->id, 'attendance_date' => '2026-10-07', 'status' => Attendance::STATUS_LEAVE]);

        $this->withToken($token)->postJson('/api/mobile/attendance/clock', ['type' => 'in', 'latitude' => 19.0, 'longitude' => 72.0])
            ->assertStatus(422);
        $this->assertSame(0, AttendancePunch::count());
    }
}
