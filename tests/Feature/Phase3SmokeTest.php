<?php

namespace Tests\Feature;

use App\Filament\Resources\ExpenseClaimResource;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\ExpenseClaimService;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase3SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
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

    public function test_admin_can_view_expense_pages(): void
    {
        $admin = $this->makeUser('ADMIN001', 'Super Admin');
        $this->actingAs($admin, 'web');

        $this->get('/admin/expense-categories')->assertStatus(200);
        $this->get('/admin/expense-claims')->assertStatus(200);
        $this->get('/admin/expense-claims/create')->assertStatus(200);
    }

    public function test_small_expense_claim_skips_department_head_and_needs_only_manager_and_finance(): void
    {
        $manager = $this->makeUser('MGR100', 'Manager');
        $employee = $this->makeUser('EMP100', 'Employee', $manager->employee_id);
        $finance = $this->makeUser('FIN100', 'Finance Admin');
        $category = ExpenseCategory::where('code', 'TAXI')->firstOrFail();

        $claim = app(ExpenseClaimService::class)->submit(
            $employee->employee,
            now()->toDateString(),
            null,
            [[
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'requested_amount' => 500,
                'description' => 'Taxi to client site',
            ]],
        );

        $this->assertEquals(ExpenseClaim::STATUS_SUBMITTED, $claim->status);
        $this->assertEquals(500.0, (float) $claim->total_requested_amount);

        $workflow = app(ApprovalWorkflowService::class);

        // Manager approves — since amount <= 10000, department head level is skipped,
        // so the next actor should be Finance directly.
        $workflow->act($claim->approvalInstance, $manager, 'approve');
        $claim->refresh();
        $this->assertEquals(ExpenseClaim::STATUS_PENDING_FINANCE, $claim->status);
        $this->assertTrue($workflow->canUserActOnInstance($claim->approvalInstance, $finance));

        // Finance approves — final level, claim fully approved with lines auto-approved.
        $workflow->act($claim->approvalInstance, $finance, 'approve');
        $claim->refresh();
        $this->assertEquals(ExpenseClaim::STATUS_APPROVED, $claim->status);
        $this->assertEquals(500.0, (float) $claim->total_approved_amount);
    }

    public function test_large_expense_claim_requires_department_head_between_manager_and_finance(): void
    {
        $manager = $this->makeUser('MGR200', 'Manager');
        $employee = $this->makeUser('EMP200', 'Employee', $manager->employee_id);
        $hrAdmin = $this->makeUser('HR200', 'HR Admin'); // department_head falls back to HR Admin role
        $finance = $this->makeUser('FIN200', 'Finance Admin');
        $category = ExpenseCategory::where('code', 'HOTEL')->firstOrFail();

        $claim = app(ExpenseClaimService::class)->submit(
            $employee->employee,
            now()->toDateString(),
            null,
            [[
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'requested_amount' => 25000,
            ]],
        );

        $workflow = app(ApprovalWorkflowService::class);

        $workflow->act($claim->approvalInstance, $manager, 'approve');
        $claim->refresh();
        $this->assertEquals(ExpenseClaim::STATUS_PENDING_FINANCE, $claim->status);

        // Finance cannot act yet — department head level is still pending for this large claim.
        $this->assertFalse($workflow->canUserActOnInstance($claim->approvalInstance, $finance));
        $this->assertTrue($workflow->canUserActOnInstance($claim->approvalInstance, $hrAdmin));

        $workflow->act($claim->approvalInstance, $hrAdmin, 'approve');
        $claim->refresh();
        $this->assertEquals(ExpenseClaim::STATUS_PENDING_FINANCE, $claim->status);

        $workflow->act($claim->approvalInstance, $finance, 'approve');
        $claim->refresh();
        $this->assertEquals(ExpenseClaim::STATUS_APPROVED, $claim->status);
    }

    public function test_payment_can_only_be_recorded_once_for_an_approved_claim(): void
    {
        $manager = $this->makeUser('MGR300', 'Manager');
        $employee = $this->makeUser('EMP300', 'Employee', $manager->employee_id);
        $finance = $this->makeUser('FIN300', 'Finance Admin');
        $category = ExpenseCategory::where('code', 'TAXI')->firstOrFail();

        $claim = app(ExpenseClaimService::class)->submit(
            $employee->employee, now()->toDateString(), null,
            [['category_id' => $category->id, 'expense_date' => now()->toDateString(), 'requested_amount' => 300]],
        );

        $workflow = app(ApprovalWorkflowService::class);
        $workflow->act($claim->approvalInstance, $manager, 'approve');
        $workflow->act($claim->approvalInstance, $finance, 'approve');
        $claim->refresh();
        $this->assertEquals(ExpenseClaim::STATUS_APPROVED, $claim->status);

        $service = app(ExpenseClaimService::class);
        $service->recordPayment($claim, 300, now()->toDateString(), 'UTR123', $finance->id);
        $claim->refresh();
        $this->assertEquals(ExpenseClaim::STATUS_PAID, $claim->status);

        $this->expectException(ValidationException::class);
        $service->recordPayment($claim, 300, now()->toDateString(), 'UTR999', $finance->id);
    }

    public function test_manager_sees_only_own_team_expense_claims(): void
    {
        $managerA = $this->makeUser('MGRX', 'Manager');
        $managerB = $this->makeUser('MGRY', 'Manager');
        $empA = $this->makeUser('EMPX', 'Employee', $managerA->employee_id);
        $empB = $this->makeUser('EMPY', 'Employee', $managerB->employee_id);
        $category = ExpenseCategory::where('code', 'MISC')->firstOrFail();

        app(ExpenseClaimService::class)->submit($empA->employee, now()->toDateString(), null, [
            ['category_id' => $category->id, 'expense_date' => now()->toDateString(), 'requested_amount' => 100],
        ]);
        app(ExpenseClaimService::class)->submit($empB->employee, now()->toDateString(), null, [
            ['category_id' => $category->id, 'expense_date' => now()->toDateString(), 'requested_amount' => 100],
        ]);

        $this->actingAs($managerA, 'web');
        $visibleIds = ExpenseClaimResource::getEloquentQuery()->pluck('employee_id')->all();
        $this->assertContains($empA->employee_id, $visibleIds);
        $this->assertNotContains($empB->employee_id, $visibleIds);
    }
}
