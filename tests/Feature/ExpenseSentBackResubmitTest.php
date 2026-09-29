<?php

namespace Tests\Feature;

use App\Filament\Resources\ExpenseClaimResource;
use App\Filament\Resources\ExpenseClaimResource\Pages\EditExpenseClaim;
use App\Models\ApprovalAction;
use App\Models\ApprovalInstance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\ExpenseClaimService;
use App\Services\ExpenseMonthlySummaryService;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseSentBackResubmitTest extends TestCase
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

    private function submitClaim(User $employee, float $amount): ExpenseClaim
    {
        $category = ExpenseCategory::where('code', 'TAXI')->firstOrFail();

        return app(ExpenseClaimService::class)->submit($employee->employee, now()->toDateString(), null, [[
            'category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'requested_amount' => $amount,
            'description' => 'Taxi',
        ]]);
    }

    public function test_rejected_and_sent_back_claims_are_excluded_from_the_monthly_summary(): void
    {
        $hr = $this->makeUser('SA900', 'Super Admin');
        $manager = $this->makeUser('MGR900', 'Manager');
        $employee = $this->makeUser('EMP900', 'Employee', $manager->employee_id);
        $workflow = app(ApprovalWorkflowService::class);

        $this->submitClaim($employee, 100);                  // pending — counts
        $rejected = $this->submitClaim($employee, 2000);
        $sentBack = $this->submitClaim($employee, 3000);

        $workflow->act($rejected->approvalInstance, $manager, ApprovalAction::ACTION_REJECT, 'Not allowed');
        $workflow->act($sentBack->approvalInstance, $manager, ApprovalAction::ACTION_SEND_BACK, 'Attach bill');

        $month = now()->format('Y-m');
        $service = app(ExpenseMonthlySummaryService::class);
        $row = collect($service->summary($month, $hr)['rows'])->firstWhere('employee_id', $employee->employee_id);

        $this->assertSame(100.0, $row['total']);

        // Day-wise still lists all three lines, but flags the non-counting ones.
        $lines = $service->dayWise($month, $employee->employee_id, $hr);
        $this->assertCount(3, $lines);
        $this->assertSame(100.0, (float) $lines->where('counts', true)->sum('requested_amount'));
    }

    public function test_sent_back_claim_can_be_edited_and_resubmitted_by_the_claimant(): void
    {
        $manager = $this->makeUser('MGR910', 'Manager');
        $employee = $this->makeUser('EMP910', 'Employee', $manager->employee_id);
        $other = $this->makeUser('EMP911', 'Employee', $manager->employee_id);
        $workflow = app(ApprovalWorkflowService::class);

        $claim = $this->submitClaim($employee, 500);
        $this->assertFalse($claim->isEditableBy($employee), 'Pending claims are not editable');

        $oldInstanceId = $claim->approval_instance_id;
        $workflow->act($claim->approvalInstance, $manager, ApprovalAction::ACTION_SEND_BACK, 'Wrong amount');
        $claim->refresh();

        $this->assertSame(ExpenseClaim::STATUS_SENT_BACK, $claim->status);
        $this->assertTrue($claim->isEditableBy($employee));
        $this->assertFalse($claim->isEditableBy($other));
        $this->assertFalse($claim->isEditableBy($manager));

        $this->actingAs($employee, 'web');
        $this->assertTrue(ExpenseClaimResource::canEdit($claim));
        $this->get(ExpenseClaimResource::getUrl('edit', ['record' => $claim]))->assertStatus(200);

        $category = ExpenseCategory::where('code', 'TAXI')->firstOrFail();
        $claim = app(ExpenseClaimService::class)->resubmit($claim, now()->toDateString(), 'Client X', [[
            'category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'requested_amount' => 450,
            'description' => 'Taxi (corrected)',
        ]]);

        $this->assertSame(ExpenseClaim::STATUS_SUBMITTED, $claim->status);
        $this->assertEquals(450.0, (float) $claim->total_requested_amount);
        $this->assertCount(1, $claim->lines);
        $this->assertNotEquals($oldInstanceId, $claim->approval_instance_id);
        $this->assertSame(ApprovalInstance::STATUS_PENDING, $claim->approvalInstance->status);
        $this->assertTrue($workflow->canUserActOnInstance($claim->approvalInstance, $manager));
        $this->assertFalse($claim->isEditableBy($employee));
    }

    public function test_edit_form_save_resubmits_the_claim(): void
    {
        $manager = $this->makeUser('MGR930', 'Manager');
        $employee = $this->makeUser('EMP930', 'Employee', $manager->employee_id);
        $claim = $this->submitClaim($employee, 800);
        app(ApprovalWorkflowService::class)->act($claim->approvalInstance, $manager, ApprovalAction::ACTION_SEND_BACK, 'Fix it');

        $this->actingAs($employee, 'web');

        $component = Livewire::test(EditExpenseClaim::class, ['record' => $claim->getRouteKey()]);
        $lines = $component->get('data.lines');
        $key = array_key_first($lines);
        $component->set("data.lines.{$key}.requested_amount", 650)
            ->call('save')
            ->assertHasNoErrors();

        $claim->refresh();
        $this->assertSame(ExpenseClaim::STATUS_SUBMITTED, $claim->status);
        $this->assertEquals(650.0, (float) $claim->total_requested_amount);
    }

    public function test_only_sent_back_claims_can_be_resubmitted(): void
    {
        $manager = $this->makeUser('MGR920', 'Manager');
        $employee = $this->makeUser('EMP920', 'Employee', $manager->employee_id);
        $claim = $this->submitClaim($employee, 500);

        app(ApprovalWorkflowService::class)->act($claim->approvalInstance, $manager, ApprovalAction::ACTION_REJECT, 'No');

        $this->expectException(ValidationException::class);
        app(ExpenseClaimService::class)->resubmit($claim->fresh(), now()->toDateString(), null, [[
            'category_id' => ExpenseCategory::first()->id,
            'expense_date' => now()->toDateString(),
            'requested_amount' => 10,
        ]]);
    }
}
