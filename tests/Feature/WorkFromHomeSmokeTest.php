<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkFromHomeRequest;
use App\Services\ApprovalWorkflowService;
use App\Services\WorkFromHomeService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkFromHomeSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $employeeCode, string $role, ?int $reportingManagerId = null): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now(),
            'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $reportingManagerId,
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

    public function test_admin_can_view_wfh_pages(): void
    {
        $admin = $this->makeUser('WADMIN001', 'Super Admin');
        $this->actingAs($admin, 'web');

        $this->get('/admin/work-from-home-requests')->assertStatus(200);
        $this->get('/admin/work-from-home-requests/create')->assertStatus(200);
        $this->get('/admin/wfh-attendance')->assertStatus(200);
        $this->get('/admin/wfh-report')->assertStatus(200);
    }

    public function test_wfh_request_approved_by_manager_marks_working_days_and_skips_weekly_off(): void
    {
        // 2024-01-01 is a Monday — pin "today" so the working-day math is deterministic
        // regardless of what day the test suite actually runs on.
        Carbon::setTestNow(Carbon::parse('2024-01-01'));

        $manager = $this->makeUser('WMGR001', 'Manager');
        $employee = $this->makeUser('WEMP001', 'Employee', $manager->employee_id);

        // Sat 2024-01-06 -> Sun 2024-01-07 is a weekend inside the range.
        $from = Carbon::parse('2024-01-05'); // Friday
        $to = Carbon::parse('2024-01-08'); // Monday

        $request = app(WorkFromHomeService::class)->request($employee->employee, $from, $to, 'Home renovation, no commute this week');

        $this->assertEquals(WorkFromHomeRequest::STATUS_PENDING, $request->status);
        $this->assertNotNull($request->approval_instance_id);
        $this->assertEquals(2, $request->total_days); // Fri + Mon, weekend excluded

        app(ApprovalWorkflowService::class)->act($request->approvalInstance, $manager, 'approve');
        $request->refresh();

        $this->assertEquals(WorkFromHomeRequest::STATUS_APPROVED, $request->status);

        $friday = Attendance::where('employee_id', $employee->employee_id)->where('attendance_date', '2024-01-05')->first();
        $saturday = Attendance::where('employee_id', $employee->employee_id)->where('attendance_date', '2024-01-06')->first();
        $monday = Attendance::where('employee_id', $employee->employee_id)->where('attendance_date', '2024-01-08')->first();

        $this->assertNotNull($friday);
        $this->assertEquals(Attendance::STATUS_WFH, $friday->status);
        $this->assertNull($saturday); // weekly off — untouched
        $this->assertNotNull($monday);
        $this->assertEquals(Attendance::STATUS_WFH, $monday->status);
    }

    public function test_self_clock_in_and_out_computes_hours_and_late_mark_without_losing_wfh_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-01 09:50:00')); // Monday, 20 min after the 09:30 shift start

        $manager = $this->makeUser('WMGR002', 'Manager');
        $employee = $this->makeUser('WEMP002', 'Employee', $manager->employee_id);

        $shift = Shift::where('name', 'General Shift')->firstOrFail();
        EmployeeShift::create([
            'employee_id' => $employee->employee_id,
            'shift_id' => $shift->id,
            'effective_from' => '2023-01-01',
        ]);

        $today = Carbon::today();
        $request = app(WorkFromHomeService::class)->request($employee->employee, $today, $today, 'WFH today');
        app(ApprovalWorkflowService::class)->act($request->approvalInstance, $manager, 'approve');

        app(WorkFromHomeService::class)->clockIn($employee->employee);

        Carbon::setTestNow(Carbon::parse('2024-01-01 18:30:00'));
        app(WorkFromHomeService::class)->clockOut($employee->employee);

        $attendance = Attendance::where('employee_id', $employee->employee_id)->where('attendance_date', $today->toDateString())->first();

        $this->assertEquals(Attendance::STATUS_WFH, $attendance->status); // never overwritten to present
        $this->assertStringStartsWith('09:50', $attendance->first_in);
        $this->assertStringStartsWith('18:30', $attendance->last_out);
        $this->assertGreaterThan(0, $attendance->late_minutes); // grace is 15 min, punched in 20 min late
        $this->assertNotNull($attendance->effective_hours);
    }

    public function test_self_punch_rejected_when_no_approved_wfh_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-01 09:30:00'));

        $employee = $this->makeUser('WEMP003', 'Employee');

        $this->expectException(ValidationException::class);
        app(WorkFromHomeService::class)->clockIn($employee->employee);
    }
}
