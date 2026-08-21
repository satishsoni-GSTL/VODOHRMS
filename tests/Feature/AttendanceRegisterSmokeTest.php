<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceMonthlySummaryService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRegisterSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $employeeCode, string $role): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now(),
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employeeCode,
            'email' => $employee->official_email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_admin_can_view_attendance_register_page(): void
    {
        $admin = $this->makeUser('RADMIN001', 'Super Admin');
        $this->actingAs($admin, 'web');

        $this->get('/admin/attendance-register')->assertStatus(200);
    }

    public function test_attendance_register_download_is_permission_gated(): void
    {
        $employee = $this->makeUser('REMP001', 'Employee');
        $this->actingAs($employee, 'web');

        $this->get('/reports/attendance_register/download?month=2024-01')->assertStatus(403);

        $admin = $this->makeUser('RADMIN002', 'Super Admin');
        $this->actingAs($admin, 'web');

        $this->get('/reports/attendance_register/download?month=2024-01')->assertStatus(200);
    }

    public function test_monthly_summary_cell_carries_first_in_last_out_and_hours(): void
    {
        $employee = $this->makeUser('REMP002', 'Employee')->employee;

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2024-01-02',
            'first_in' => '09:15:00',
            'last_out' => '18:05:00',
            'effective_hours' => 7.83,
            'status' => Attendance::STATUS_PRESENT,
            'source' => 'manual',
        ]);

        $days = app(AttendanceMonthlySummaryService::class)->buildForEmployee(
            $employee,
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-01-31'),
        );

        $cell = $days['2024-01-02'];

        $this->assertEquals('09:15:00', $cell['first_in']);
        $this->assertEquals('18:05:00', $cell['last_out']);
        $this->assertEquals(7.83, $cell['hours']);

        // A day with no attendance row at all still carries the null keys (weekly off here).
        $weeklyOffDay = $days['2024-01-06']; // a Saturday
        $this->assertNull($weeklyOffDay['first_in']);
        $this->assertNull($weeklyOffDay['hours']);
    }
}
