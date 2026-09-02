<?php

namespace Tests\Feature;

use App\Filament\Resources\LeaveApplicationResource;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\AttendanceRegularizationService;
use App\Services\LeaveApplicationService;
use App\Services\LeaveBalanceService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase2SmokeTest extends TestCase
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

    private function makeAdmin(): User
    {
        return $this->makeUser('ADMIN001', 'Super Admin');
    }

    public function test_admin_can_view_shift_holiday_leave_type_lists(): void
    {
        $this->actingAs($this->makeAdmin(), 'web');

        $this->get('/admin/shifts')->assertStatus(200);
        $this->get('/admin/holidays')->assertStatus(200);
        $this->get('/admin/leave-types')->assertStatus(200);
        $this->get('/admin/attendances')->assertStatus(200);
        $this->get('/admin/attendance-regularizations')->assertStatus(200);
        $this->get('/admin/leave-applications')->assertStatus(200);
        $this->get('/admin/employee-leave-balances')->assertStatus(200);
        $this->get('/admin/pending-approvals')->assertStatus(200);
    }

    public function test_attendance_regularization_is_approved_by_a_single_approver_and_corrects_attendance(): void
    {
        $manager = $this->makeUser('MGR001', 'Manager');
        $employee = $this->makeUser('EMP001', 'Employee', $manager->employee_id);
        $this->makeUser('HR001', 'HR Admin');

        $date = Carbon::today()->subDay();

        $regularization = app(AttendanceRegularizationService::class)->request(
            $employee->employee,
            $date,
            'missing_punch',
            ['first_in' => '09:30', 'last_out' => '18:30'],
            'Forgot to punch in',
            null,
        );

        $this->assertEquals('pending', $regularization->status);
        $this->assertNotNull($regularization->approval_instance_id);

        $workflow = app(ApprovalWorkflowService::class);

        // Random employee (not the reporting manager) cannot act on it.
        $stranger = $this->makeUser('EMP002', 'Employee');
        $this->assertFalse($workflow->canUserActOnInstance($regularization->approvalInstance, $stranger));

        // A single approval — here the reporting manager — finalises it and corrects attendance.
        $workflow->act($regularization->approvalInstance, $manager, 'approve');
        $regularization->refresh();
        $this->assertEquals('approved', $regularization->status);

        $attendance = Attendance::where('employee_id', $employee->employee_id)
            ->where('attendance_date', $date->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertStringStartsWith('09:30', $attendance->first_in);
        $this->assertNotNull($attendance->effective_hours);
    }

    public function test_hr_alone_can_approve_an_attendance_regularization(): void
    {
        $manager = $this->makeUser('MGR003', 'Manager');
        $employee = $this->makeUser('EMP003', 'Employee', $manager->employee_id);
        $hrAdmin = $this->makeUser('HR003', 'HR Admin');

        $date = Carbon::today()->subDay();

        $regularization = app(AttendanceRegularizationService::class)->request(
            $employee->employee, $date, 'missing_punch',
            ['first_in' => '09:15', 'last_out' => '18:15'], 'Forgot', null,
        );

        app(ApprovalWorkflowService::class)->act($regularization->approvalInstance, $hrAdmin, 'approve');

        $this->assertEquals('approved', $regularization->fresh()->status);
    }

    public function test_leave_application_debits_balance_only_after_final_approval(): void
    {
        $manager = $this->makeUser('MGR002', 'Manager');
        $employee = $this->makeUser('EMP010', 'Employee', $manager->employee_id);

        $leaveType = LeaveType::where('code', 'CL')->firstOrFail();
        app(LeaveBalanceService::class)->credit($employee->employee, $leaveType, now()->year, 12, 'Annual credit');

        $application = app(LeaveApplicationService::class)->apply(
            $employee->employee,
            $leaveType,
            Carbon::today()->addDays(5),
            Carbon::today()->addDays(6),
            false,
            null,
            'Personal work',
            null,
        );

        $this->assertEquals('pending', $application->status);
        $this->assertEquals(2.0, (float) $application->days);

        $balanceBefore = EmployeeLeaveBalance::where('employee_id', $employee->employee_id)
            ->where('leave_type_id', $leaveType->id)->first();
        $this->assertEquals(12.0, (float) $balanceBefore->closing_balance);

        app(ApprovalWorkflowService::class)->act($application->approvalInstance, $manager, 'approve');

        $application->refresh();
        $this->assertEquals(LeaveApplication::STATUS_APPROVED, $application->status);

        $balanceAfter = EmployeeLeaveBalance::where('employee_id', $employee->employee_id)
            ->where('leave_type_id', $leaveType->id)->first();
        $this->assertEquals(10.0, (float) $balanceAfter->closing_balance);
    }

    public function test_leave_application_rejects_when_insufficient_balance(): void
    {
        $manager = $this->makeUser('MGR003', 'Manager');
        $employee = $this->makeUser('EMP020', 'Employee', $manager->employee_id);
        $leaveType = LeaveType::where('code', 'CL')->firstOrFail();

        // No balance credited — closing_balance is 0.
        $application = app(LeaveApplicationService::class)->apply(
            $employee->employee,
            $leaveType,
            Carbon::today()->addDays(1),
            Carbon::today()->addDays(1),
            false,
            null,
            'Test',
            null,
        );

        $this->expectException(ValidationException::class);
        app(ApprovalWorkflowService::class)->act($application->approvalInstance, $manager, 'approve');
    }

    public function test_manager_sees_only_own_team_leave_applications(): void
    {
        $managerA = $this->makeUser('MGRA', 'Manager');
        $managerB = $this->makeUser('MGRB', 'Manager');
        $empA = $this->makeUser('EMPA', 'Employee', $managerA->employee_id);
        $empB = $this->makeUser('EMPB', 'Employee', $managerB->employee_id);

        $leaveType = LeaveType::where('code', 'CL')->firstOrFail();
        app(LeaveApplicationService::class)->apply($empA->employee, $leaveType, Carbon::today(), Carbon::today(), false, null, 'A', null);
        app(LeaveApplicationService::class)->apply($empB->employee, $leaveType, Carbon::today(), Carbon::today(), false, null, 'B', null);

        $this->actingAs($managerA, 'web');
        $visibleIds = LeaveApplicationResource::getEloquentQuery()->pluck('employee_id')->all();
        $this->assertContains($empA->employee_id, $visibleIds);
        $this->assertNotContains($empB->employee_id, $visibleIds);
    }
}
