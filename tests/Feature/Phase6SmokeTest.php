<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeLoan;
use App\Models\ExitClearance;
use App\Models\FullFinalSettlement;
use App\Models\Resignation;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\ExitClearanceService;
use App\Services\FnFSettlementService;
use App\Services\LoanService;
use App\Services\OnboardingService;
use App\Services\PayrollCalculationService;
use App\Services\ResignationService;
use App\Services\SalaryStructureService;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\Phase5Seeder;
use Database\Seeders\Phase6Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6SmokeTest extends TestCase
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

    public function test_admin_can_view_phase6_pages(): void
    {
        $admin = $this->makeUser('ADMIN001', 'Super Admin');
        $this->actingAs($admin, 'web');

        $this->get('/admin/onboarding-checklists')->assertStatus(200);
        $this->get('/admin/employee-assets')->assertStatus(200);
        $this->get('/admin/employee-loans')->assertStatus(200);
        $this->get('/admin/employee-loans/create')->assertStatus(200);
        $this->get('/admin/resignations')->assertStatus(200);
        $this->get('/admin/resignations/create')->assertStatus(200);
        $this->get('/admin/exit-clearances')->assertStatus(200);
        $this->get('/admin/full-final-settlements')->assertStatus(200);
    }

    public function test_onboarding_checklist_refresh_computes_completion_from_actual_data(): void
    {
        $employeeUser = $this->makeUser('EMP600', 'Employee');
        $employee = $employeeUser->employee;

        $checklist = app(OnboardingService::class)->refresh($employee);

        // Fresh employee: only login_done (a User row exists) should be true.
        $this->assertFalse($checklist->documents_done);
        $this->assertFalse($checklist->statutory_done);
        $this->assertFalse($checklist->bank_done);
        $this->assertTrue($checklist->login_done);
        $this->assertLessThan(100, $checklist->completion_percent);

        EmployeeAsset::create([
            'employee_id' => $employee->id,
            'asset_type' => 'Laptop',
            'allocated_on' => now(),
        ]);

        $checklist = app(OnboardingService::class)->refresh($employee->fresh());
        $this->assertTrue($checklist->asset_allocation_done);
    }

    public function test_loan_request_flows_through_manager_hr_finance_and_recovers_in_payroll(): void
    {
        $manager = $this->makeUser('MGR600', 'Manager');
        $employeeUser = $this->makeUser('EMP610', 'Employee', $manager->employee_id);
        $hrAdmin = $this->makeUser('HR600', 'HR Admin');
        $financeAdmin = $this->makeUser('FIN600', 'Finance Admin');

        $loanService = app(LoanService::class);
        $workflow = app(ApprovalWorkflowService::class);

        $loan = $loanService->request($employeeUser->employee, EmployeeLoan::TYPE_LOAN, 6000, 'Medical emergency', now()->toDateString());
        $this->assertEquals(EmployeeLoan::STATUS_PENDING, $loan->status);

        $workflow->act($loan->approvalInstance, $manager, 'approve');
        $loan->refresh();
        $this->assertEquals(EmployeeLoan::STATUS_MANAGER_APPROVED, $loan->status);

        $workflow->act($loan->approvalInstance, $hrAdmin, 'approve');
        $loan->refresh();
        $this->assertEquals(EmployeeLoan::STATUS_HR_APPROVED, $loan->status);

        // HR/Payroll fills in the recovery schedule before Finance gives the final sign-off.
        $loan->update(['approved_amount' => 6000, 'installments' => 3, 'monthly_recovery' => 2000]);

        $workflow->act($loan->approvalInstance, $financeAdmin, 'approve');
        $loan->refresh();
        $this->assertEquals(EmployeeLoan::STATUS_ACTIVE, $loan->status);
        $this->assertEquals(6000, (float) $loan->outstanding_balance);

        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $payrollMonth = now()->format('Y-m');

        app(SalaryStructureService::class)->assign(
            $employeeUser->employee, now()->subYears(2)->toDateString(), 300000, [$basic->id => 20000],
        );

        $payroll = app(PayrollCalculationService::class);
        $run = $payroll->getOrCreateRun($payrollMonth, $company->id);
        $payroll->calculate($run);

        $runEmployee = $run->employees()->where('employee_id', $employeeUser->employee_id)->firstOrFail();
        $recoveryLine = $runEmployee->lines()->where('label', 'Loan Recovery')->first();

        $this->assertNotNull($recoveryLine);
        $this->assertEquals(2000, (float) $recoveryLine->amount);

        $loan->refresh();
        $this->assertEquals(4000, (float) $loan->outstanding_balance);

        // Recalculating before finalization must not double-recover.
        $payroll->calculate($run);
        $this->assertEquals(4000, (float) $loan->fresh()->outstanding_balance);
        $this->assertEquals(1, $runEmployee->lines()->where('label', 'Loan Recovery')->count());
    }

    public function test_resignation_submission_exit_clearance_and_fnf_lifecycle(): void
    {
        $manager = $this->makeUser('MGR620', 'Manager');
        $employeeUser = $this->makeUser('EMP620', 'Employee', $manager->employee_id);
        $hrAdmin = $this->makeUser('HR620', 'HR Admin');
        $employee = $employeeUser->employee;

        $resignationService = app(ResignationService::class);
        $workflow = app(ApprovalWorkflowService::class);

        $lastWorkingDate = now()->addDays(30)->toDateString();
        $resignation = $resignationService->submit($employee, now()->toDateString(), 'Better opportunity', $lastWorkingDate);

        $this->assertEquals(Resignation::STATUS_PENDING, $resignation->status);
        $this->assertEquals(5, $resignation->exitClearances()->count());

        $workflow->act($resignation->approvalInstance, $manager, 'approve');
        $resignation->refresh();
        $this->assertEquals(Resignation::STATUS_MANAGER_APPROVED, $resignation->status);

        $workflow->act($resignation->approvalInstance, $hrAdmin, 'approve');
        $resignation->refresh();
        $this->assertEquals(Resignation::STATUS_HR_APPROVED, $resignation->status);
        $this->assertEquals($lastWorkingDate, $resignation->approved_last_working_date->toDateString());

        $exitService = app(ExitClearanceService::class);
        foreach ($resignation->exitClearances as $clearance) {
            $exitService->clear($clearance, $hrAdmin, 'All good');
        }
        $this->assertTrue($exitService->allCleared($resignation->fresh()));

        // Give the employee an outstanding loan so F&F recovers it automatically.
        EmployeeLoan::create([
            'employee_id' => $employee->id,
            'type' => EmployeeLoan::TYPE_LOAN,
            'requested_amount' => 5000,
            'reason' => 'Test loan',
            'request_date' => now()->subMonths(2)->toDateString(),
            'approved_amount' => 5000,
            'outstanding_balance' => 5000,
            'monthly_recovery' => 5000,
            'installments' => 1,
            'status' => EmployeeLoan::STATUS_ACTIVE,
        ]);

        $fnfService = app(FnFSettlementService::class);
        $settlement = $fnfService->calculate($resignation->fresh(), $hrAdmin);

        $this->assertEquals(FullFinalSettlement::STATUS_CALCULATED, $settlement->status);
        $this->assertEquals(5000, (float) $settlement->loan_recovery);
        $this->assertEquals(0, (float) $settlement->notice_recovery); // last working date honors full notice period
        $this->assertEquals(-5000, (float) $settlement->final_amount);

        $fnfService->approve($settlement, $hrAdmin);
        $settlement->refresh();
        $this->assertEquals(FullFinalSettlement::STATUS_APPROVED, $settlement->status);

        $fnfService->markPaid($settlement);
        $settlement->refresh();
        $this->assertEquals(FullFinalSettlement::STATUS_PAID, $settlement->status);
        $this->assertNotNull($settlement->paid_at);
        $this->assertEquals(Employee::STATUS_EXITED, $employee->fresh()->status);
    }

    public function test_exit_clearance_can_be_rejected_and_reflects_in_status(): void
    {
        $manager = $this->makeUser('MGR630', 'Manager');
        $employeeUser = $this->makeUser('EMP630', 'Employee', $manager->employee_id);
        $hrAdmin = $this->makeUser('HR630', 'HR Admin');

        $resignation = app(ResignationService::class)->submit(
            $employeeUser->employee, now()->toDateString(), 'Relocation', now()->addDays(30)->toDateString(),
        );

        $itClearance = $resignation->exitClearances()->where('department', 'it')->firstOrFail();
        app(ExitClearanceService::class)->reject($itClearance, $hrAdmin, 'Laptop not returned');

        $itClearance->refresh();
        $this->assertEquals(ExitClearance::STATUS_REJECTED, $itClearance->status);
        $this->assertFalse(app(ExitClearanceService::class)->allCleared($resignation->fresh()));
    }
}
