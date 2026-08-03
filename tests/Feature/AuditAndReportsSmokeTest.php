<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\LeaveApplicationService;
use App\Services\LeaveBalanceService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditAndReportsSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
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

    public function test_admin_can_view_audit_log_and_reports_pages(): void
    {
        $admin = $this->makeUser('ADMIN001', 'Super Admin');
        $this->actingAs($admin, 'web');

        $this->get('/admin/audit-logs')->assertStatus(200);
        $this->get('/admin/reports')->assertStatus(200);

        Attendance::create([
            'employee_id' => $admin->employee_id,
            'attendance_date' => now()->startOfMonth()->addDays(1)->toDateString(),
            'first_in' => '09:05:00',
            'last_out' => '18:10:00',
            'status' => Attendance::STATUS_PRESENT,
            'source' => 'biometric',
        ]);

        $response = $this->get('/admin/attendance-monthly-view');
        $response->assertStatus(200);
        // Colors must be inline styles, not Tailwind utility classes: Filament's pre-compiled
        // panel CSS doesn't ship arbitrary semantic-color classes like bg-success-50.
        $response->assertSee('background-color:#C6EFCE', false);
    }

    public function test_plain_employee_is_blocked_from_audit_logs(): void
    {
        $employee = $this->makeUser('EMP700', 'Employee');
        $this->actingAs($employee, 'web');

        $this->get('/admin/audit-logs')->assertStatus(403);
    }

    public function test_updating_an_employee_writes_an_audit_log_entry(): void
    {
        $admin = $this->makeUser('ADMIN001', 'Super Admin');
        $employee = $this->makeUser('EMP710', 'Employee');

        $this->actingAs($admin, 'web');
        $employee->employee->update(['first_name' => 'UpdatedName']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update',
            'module' => 'employee',
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->employee_id,
        ]);

        $log = AuditLog::where('auditable_id', $employee->employee_id)
            ->where('action', 'update')
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals('UpdatedName', $log->new_values['first_name']);
    }

    public function test_leave_submission_and_approval_each_write_an_audit_log_entry(): void
    {
        $manager = $this->makeUser('MGR700', 'Manager');
        $employee = $this->makeUser('EMP720', 'Employee', $manager->employee_id);
        $leaveType = LeaveType::where('code', 'CL')->firstOrFail();

        app(LeaveBalanceService::class)->credit($employee->employee, $leaveType, now()->year, 12, 'Annual credit');

        $this->actingAs($employee, 'web');
        $application = app(LeaveApplicationService::class)->apply(
            $employee->employee, $leaveType, Carbon::today()->addDays(2), Carbon::today()->addDays(2),
            false, null, 'Personal', null,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'module' => 'leave',
            'auditable_type' => LeaveApplication::class,
            'auditable_id' => $application->id,
        ]);

        $this->actingAs($manager, 'web');
        app(ApprovalWorkflowService::class)->act($application->approvalInstance, $manager, 'approve');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approve',
            'module' => 'leave',
            'auditable_type' => LeaveApplication::class,
            'auditable_id' => $application->id,
        ]);
    }

    public function test_report_downloads_are_permission_gated(): void
    {
        $hrAdmin = $this->makeUser('HR700', 'HR Admin');
        $this->actingAs($hrAdmin, 'web');

        $response = $this->get('/reports/employee/download');
        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));

        $manager = $this->makeUser('MGR710', 'Manager');
        $this->makeUser('EMP730', 'Employee', $manager->employee_id);
        $this->actingAs($manager, 'web');

        // Managers have no explicit permissions, but team-scoped reports open up because they have direct reports.
        $this->get('/reports/attendance/download')->assertStatus(200);

        // The employee master report is HR/Payroll/Finance-tier only — a manager may not download it.
        $this->get('/reports/employee/download')->assertStatus(403);

        $plainEmployee = $this->makeUser('EMP740', 'Employee');
        $this->actingAs($plainEmployee, 'web');
        $this->get('/reports/attendance/download')->assertStatus(403);

        $this->get('/reports/unknown-type/download')->assertStatus(404);
    }
}
