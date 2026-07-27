<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource\Pages\EditEmployee;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\ExpenseCategory;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Notifications\ApprovalActionRequiredNotification;
use App\Notifications\ApprovalOutcomeNotification;
use App\Notifications\ExitClearanceAssignedNotification;
use App\Notifications\FnfSettlementNotification;
use App\Notifications\PayslipReadyNotification;
use App\Notifications\WelcomeAccountNotification;
use App\Services\ApprovalWorkflowService;
use App\Services\ExpenseClaimService;
use App\Services\FnFSettlementService;
use App\Services\LeaveApplicationService;
use App\Services\LeaveBalanceService;
use App\Services\PayrollCalculationService;
use App\Services\ResignationService;
use App\Services\SalaryStructureService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\Phase5Seeder;
use Database\Seeders\Phase6Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MailNotificationsSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
        $this->seed(Phase4Seeder::class);
        $this->seed(Phase5Seeder::class);
        $this->seed(Phase6Seeder::class);
        Notification::fake();
    }

    private function makeUser(string $employeeCode, string $role, ?int $reportingManagerId = null): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now()->subYear(),
            'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $reportingManagerId,
            'notice_period_days' => 30,
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

    public function test_leave_submission_notifies_manager_and_approval_notifies_requester(): void
    {
        $manager = $this->makeUser('MGR700', 'Manager');
        $employeeUser = $this->makeUser('EMP700', 'Employee', $manager->employee_id);

        $leaveType = LeaveType::where('code', 'CL')->firstOrFail();
        app(LeaveBalanceService::class)->credit($employeeUser->employee, $leaveType, now()->year, 12, 'Annual credit');

        $application = app(LeaveApplicationService::class)->apply(
            $employeeUser->employee, $leaveType, Carbon::today()->addDays(2), Carbon::today()->addDays(2),
            false, null, 'Personal work', null,
        );

        Notification::assertSentTo($manager, ApprovalActionRequiredNotification::class);
        Notification::assertNotSentTo($employeeUser, ApprovalActionRequiredNotification::class);

        app(ApprovalWorkflowService::class)->act($application->approvalInstance, $manager, 'approve');

        Notification::assertSentTo($employeeUser, ApprovalOutcomeNotification::class);
    }

    public function test_leave_rejection_notifies_requester(): void
    {
        $manager = $this->makeUser('MGR701', 'Manager');
        $employeeUser = $this->makeUser('EMP701', 'Employee', $manager->employee_id);

        $leaveType = LeaveType::where('code', 'CL')->firstOrFail();
        app(LeaveBalanceService::class)->credit($employeeUser->employee, $leaveType, now()->year, 12, 'Annual credit');

        $application = app(LeaveApplicationService::class)->apply(
            $employeeUser->employee, $leaveType, Carbon::today()->addDays(3), Carbon::today()->addDays(3),
            false, null, 'Personal work', null,
        );

        app(ApprovalWorkflowService::class)->act($application->approvalInstance, $manager, 'reject', 'Team is short-staffed that week');

        Notification::assertSentTo($employeeUser, ApprovalOutcomeNotification::class);
    }

    public function test_expense_approval_notifies_next_level_approver(): void
    {
        $manager = $this->makeUser('MGR710', 'Manager');
        $employeeUser = $this->makeUser('EMP710', 'Employee', $manager->employee_id);
        $finance = $this->makeUser('FIN710', 'Finance Admin');
        $category = ExpenseCategory::where('code', 'TAXI')->firstOrFail();

        $claim = app(ExpenseClaimService::class)->submit(
            $employeeUser->employee, now()->toDateString(), null,
            [['category_id' => $category->id, 'expense_date' => now()->toDateString(), 'requested_amount' => 500]],
        );

        Notification::assertSentTo($manager, ApprovalActionRequiredNotification::class);
        Notification::assertNotSentTo($finance, ApprovalActionRequiredNotification::class);

        app(ApprovalWorkflowService::class)->act($claim->approvalInstance, $manager, 'approve');

        Notification::assertSentTo($finance, ApprovalActionRequiredNotification::class);
    }

    public function test_payroll_finalize_sends_payslip_ready_to_employees(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $employeeUser = $this->makeUser('EMP720', 'Employee');
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();

        app(SalaryStructureService::class)->assign(
            $employeeUser->employee, now()->subYears(2)->toDateString(), 300000, [$basic->id => 20000],
        );

        $payroll = app(PayrollCalculationService::class);
        $run = $payroll->getOrCreateRun(now()->format('Y-m'), $company->id);
        $payroll->calculate($run);
        $run->update(['status' => PayrollRun::STATUS_REVIEWED]);

        $payroll->finalize($run);

        Notification::assertSentTo($employeeUser, PayslipReadyNotification::class);
    }

    public function test_resignation_submission_notifies_exit_clearance_owners(): void
    {
        $manager = $this->makeUser('MGR730', 'Manager');
        $employeeUser = $this->makeUser('EMP730', 'Employee', $manager->employee_id);
        $hrAdmin = $this->makeUser('HR730', 'HR Admin');
        $financeAdmin = $this->makeUser('FIN730', 'Finance Admin');

        app(ResignationService::class)->submit(
            $employeeUser->employee, now()->toDateString(), 'Better opportunity', now()->addDays(30)->toDateString(),
        );

        Notification::assertSentTo($manager, ExitClearanceAssignedNotification::class);
        Notification::assertSentTo($hrAdmin, ExitClearanceAssignedNotification::class);
        Notification::assertSentTo($financeAdmin, ExitClearanceAssignedNotification::class);
    }

    public function test_fnf_approve_and_mark_paid_notify_employee(): void
    {
        $manager = $this->makeUser('MGR740', 'Manager');
        $employeeUser = $this->makeUser('EMP740', 'Employee', $manager->employee_id);
        $hrAdmin = $this->makeUser('HR740', 'HR Admin');
        $employee = $employeeUser->employee;

        $resignationService = app(ResignationService::class);
        $workflow = app(ApprovalWorkflowService::class);

        $lastWorkingDate = now()->addDays(30)->toDateString();
        $resignation = $resignationService->submit($employee, now()->toDateString(), 'Relocation', $lastWorkingDate);

        $workflow->act($resignation->approvalInstance, $manager, 'approve');
        $workflow->act($resignation->fresh()->approvalInstance, $hrAdmin, 'approve');

        EmployeeLoan::create([
            'employee_id' => $employee->id,
            'type' => EmployeeLoan::TYPE_LOAN,
            'requested_amount' => 1000,
            'reason' => 'Test loan',
            'request_date' => now()->subMonths(2)->toDateString(),
            'approved_amount' => 1000,
            'outstanding_balance' => 1000,
            'monthly_recovery' => 1000,
            'installments' => 1,
            'status' => EmployeeLoan::STATUS_ACTIVE,
        ]);

        $fnfService = app(FnFSettlementService::class);
        $settlement = $fnfService->calculate($resignation->fresh(), $hrAdmin);

        $fnfService->approve($settlement, $hrAdmin);
        Notification::assertSentTo($employeeUser, FnfSettlementNotification::class);

        $fnfService->markPaid($settlement->fresh());
        Notification::assertSentTo($employeeUser->fresh(), FnfSettlementNotification::class);
    }

    public function test_create_login_action_creates_user_and_sends_welcome_email(): void
    {
        $admin = $this->makeUser('ADMIN700', 'Super Admin');
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => 'EMP750',
            'first_name' => 'EMP750',
            'official_email' => 'emp750@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now(),
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin, 'web');

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->callAction('createLogin');

        $this->assertTrue($employee->user()->exists());

        Notification::assertSentTo($employee->fresh()->user, WelcomeAccountNotification::class);
    }
}
