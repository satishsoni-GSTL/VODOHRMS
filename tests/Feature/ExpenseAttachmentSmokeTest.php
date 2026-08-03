<?php

namespace Tests\Feature;

use App\Filament\Resources\ExpenseClaimResource;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\ExpenseClaimService;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseAttachmentSmokeTest extends TestCase
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

    public function test_view_page_and_approval_modal_expose_the_receipt_and_line_details(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('expense-receipts/taxi-bill.pdf', 'fake-pdf-bytes');

        $manager = $this->makeUser('MGR900', 'Manager');
        $employee = $this->makeUser('EMP900', 'Employee', $manager->employee_id);
        $category = ExpenseCategory::where('code', 'TAXI')->firstOrFail();

        $claim = app(ExpenseClaimService::class)->submit(
            $employee->employee, now()->toDateString(), null,
            [[
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'requested_amount' => 500,
                'vendor' => 'Ola Cabs',
                'receipt_path' => 'expense-receipts/taxi-bill.pdf',
            ]],
        );
        $line = $claim->lines()->firstOrFail();

        // The View page must surface the receipt as a downloadable link.
        $this->actingAs($manager, 'web');
        $this->get(ExpenseClaimResource::getUrl('view', ['record' => $claim]))
            ->assertStatus(200)
            ->assertSee('Download Receipt')
            ->assertSee(route('expense-receipts.download', $line), false);

        // The approve/reject modal content (shared HasApprovalActions trait) must show line details.
        $modalHtml = ExpenseClaimResourceApprovalContentAccessor::render($claim);
        $this->assertStringContainsString('Ola Cabs', $modalHtml);
        $this->assertStringContainsString(route('expense-receipts.download', $line), $modalHtml);

        // The claim's own manager (an approver in the chain) can download the receipt.
        $this->get(route('expense-receipts.download', $line))->assertStatus(200);

        // An unrelated employee with no visibility into this claim is blocked.
        $stranger = $this->makeUser('EMP901', 'Employee');
        $this->actingAs($stranger, 'web');
        $this->get(route('expense-receipts.download', $line))->assertStatus(403);
    }
}

/**
 * ExpenseClaimResource::approvalModalContent() is protected (it's an override point for the
 * shared HasApprovalActions trait, not public API) — this thin accessor lets the test render
 * it via reflection without weakening the method's visibility for production code.
 */
class ExpenseClaimResourceApprovalContentAccessor extends ExpenseClaimResource
{
    public static function render($record): string
    {
        return static::approvalModalContent($record)?->render() ?? '';
    }
}
