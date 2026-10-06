<?php

namespace Tests\Feature;

use App\Filament\Pages\BulkAttendancePunch;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkAttendancePunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-30 12:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $employeeCode, string $role): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => '2026-01-01',
            'status' => Employee::STATUS_ACTIVE,
            'weekly_off' => ['saturday', 'sunday'],
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

    public function test_admin_can_open_page_by_url_and_it_is_not_in_navigation(): void
    {
        $this->assertFalse(BulkAttendancePunch::shouldRegisterNavigation());

        $this->actingAs($this->makeUser('BADM001', 'Super Admin'), 'web');
        $this->get('/admin/bulk-attendance-punch')->assertOk();
    }

    public function test_employee_cannot_open_page(): void
    {
        $this->actingAs($this->makeUser('BEMP001', 'Employee'), 'web');
        $this->get('/admin/bulk-attendance-punch')->assertForbidden();
    }

    public function test_marks_only_absent_working_days_present(): void
    {
        $admin = $this->makeUser('BADM002', 'Super Admin');
        $employee = $this->makeUser('BEMP002', 'Employee')->employee;

        // Mon 21 – Fri 25 Sep 2026 (+ weekend 26/27): 21 present, 22 absent, 23 leave, 24/25 no record.
        Attendance::create(['employee_id' => $employee->id, 'attendance_date' => '2026-09-21', 'first_in' => '09:30:00', 'last_out' => '18:30:00', 'status' => Attendance::STATUS_PRESENT]);
        Attendance::create(['employee_id' => $employee->id, 'attendance_date' => '2026-09-22', 'status' => Attendance::STATUS_ABSENT]);
        Attendance::create(['employee_id' => $employee->id, 'attendance_date' => '2026-09-23', 'status' => Attendance::STATUS_LEAVE]);

        $this->actingAs($admin, 'web');

        Livewire::test(BulkAttendancePunch::class)
            ->fillForm(['employee_id' => $employee->id, 'from_date' => '2026-09-21', 'to_date' => '2026-09-27'])
            ->assertFormSet(['dates' => ['2026-09-22', '2026-09-24', '2026-09-25']])
            ->fillForm(['in_time' => '10:00', 'out_time' => '19:00', 'remarks' => 'Device down'])
            ->call('submit')
            ->assertHasNoFormErrors();

        foreach (['2026-09-22', '2026-09-24', '2026-09-25'] as $date) {
            $row = Attendance::where('employee_id', $employee->id)->where('attendance_date', $date)->first();
            $this->assertSame(Attendance::STATUS_PRESENT, $row->status, $date);
            $this->assertSame('10:00:00', $row->first_in);
            $this->assertSame('19:00:00', $row->last_out);
            $this->assertCount(2, $row->punches);
        }

        $this->assertSame(Attendance::STATUS_LEAVE, Attendance::where('employee_id', $employee->id)->where('attendance_date', '2026-09-23')->value('status'));
        $this->assertFalse(Attendance::where('employee_id', $employee->id)->where('attendance_date', '2026-09-26')->exists());
    }
}
