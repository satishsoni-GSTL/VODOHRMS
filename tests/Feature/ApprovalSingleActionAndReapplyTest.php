<?php

namespace Tests\Feature;

use App\Filament\Resources\LeaveApplicationResource;
use App\Filament\Resources\LeaveApplicationResource\Pages\CreateLeaveApplication;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowLevel;
use App\Services\ApprovalWorkflowService;
use App\Services\LeaveApplicationService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalSingleActionAndReapplyTest extends TestCase
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

    private function applyLeave(User $employee, string $leaveTypeCode = 'CL'): LeaveApplication
    {
        return app(LeaveApplicationService::class)->apply(
            $employee->employee,
            LeaveType::where('code', $leaveTypeCode)->firstOrFail(),
            Carbon::parse('2030-01-07'),
            Carbon::parse('2030-01-08'),
            false,
            null,
            'Family function',
            null,
        );
    }

    public function test_buttons_hide_after_send_back_and_employee_can_reapply_with_prefilled_form(): void
    {
        $manager = $this->makeUser('AMGR001', 'Manager');
        $employee = $this->makeUser('AEMP001', 'Employee', $manager->employee_id);
        $workflow = app(ApprovalWorkflowService::class);

        $leave = $this->applyLeave($employee);
        $this->assertTrue($workflow->canUserActOnInstance($leave->approvalInstance, $manager));

        $workflow->act($leave->approvalInstance, $manager, 'send_back', 'Wrong leave type');
        $leave->refresh();

        $this->assertSame(LeaveApplication::STATUS_SENT_BACK, $leave->status);
        $this->assertFalse($workflow->canUserActOnInstance($leave->approvalInstance->fresh(), $manager));

        $this->actingAs($employee, 'web');
        $this->assertTrue(LeaveApplicationResource::canReapply($leave));

        Livewire::withQueryParams(['reapply' => $leave->id])
            ->test(CreateLeaveApplication::class)
            ->assertFormSet([
                'employee_id' => $employee->employee_id,
                'leave_type_id' => $leave->leave_type_id,
                'from_date' => '2030-01-07',
                'to_date' => '2030-01-08',
                'reason' => 'Family function',
            ]);

        // A colleague can't reapply someone else's request.
        $this->actingAs($this->makeUser('AEMP002', 'Employee', $manager->employee_id), 'web');
        $this->assertFalse(LeaveApplicationResource::canReapply($leave));
    }

    public function test_approver_cannot_act_twice_and_same_approver_clears_following_levels(): void
    {
        $manager = $this->makeUser('AMGR002', 'Manager');
        $employee = $this->makeUser('AEMP003', 'Employee', $manager->employee_id);
        $workflow = app(ApprovalWorkflowService::class);

        // Level 2 is the same person as level 1 (the reporting manager).
        WorkflowLevel::create([
            'workflow_definition_id' => WorkflowDefinition::where('module', WorkflowDefinition::MODULE_LEAVE)->value('id'),
            'sequence' => 2,
            'approver_type' => WorkflowLevel::APPROVER_SPECIFIC_USER,
            'approver_user_id' => $manager->id,
        ]);

        $leave = $this->applyLeave($employee, 'LWP');
        $workflow->act($leave->approvalInstance, $manager, 'approve');

        $instance = $leave->approvalInstance->fresh();
        $this->assertSame(LeaveApplication::STATUS_APPROVED, $leave->fresh()->status);
        $this->assertCount(2, $instance->actions);
        $this->assertFalse($workflow->canUserActOnInstance($instance, $manager));
    }
}
