<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\ExpenseClaimLine;
use App\Models\User;
use App\Services\ExpenseMonthlySummaryService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseMonthlySummaryReportTest extends TestCase
{
    use RefreshDatabase;

    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
        $this->month = now()->format('Y-m');
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

    private function claimFor(Employee $employee, array $lines): ExpenseClaim
    {
        $claim = ExpenseClaim::create([
            'claim_number' => 'EXP-'.$employee->id.'-'.uniqid(),
            'employee_id' => $employee->id,
            'claim_date' => Carbon::parse($this->month.'-01'),
            'status' => ExpenseClaim::STATUS_APPROVED,
        ]);

        foreach ($lines as $line) {
            ExpenseClaimLine::create([
                'expense_claim_id' => $claim->id,
                'category_id' => $line['category_id'],
                'expense_date' => $line['date'],
                'requested_amount' => $line['amount'],
                'approved_amount' => $line['amount'],
            ]);
        }

        $claim->recalculateTotals();
        $claim->save();

        return $claim;
    }

    public function test_summary_service_pivots_amounts_by_employee_and_head(): void
    {
        $hr = $this->makeUser('SA001', 'Super Admin');
        $employee = $this->makeUser('EMP800', 'Employee')->employee;

        $travel = ExpenseCategory::where('code', 'TRAVEL_LOCAL')->firstOrFail();
        $food = ExpenseCategory::where('code', 'FOOD')->firstOrFail();

        $this->claimFor($employee, [
            ['category_id' => $travel->id, 'date' => $this->month.'-03', 'amount' => 200],
            ['category_id' => $travel->id, 'date' => $this->month.'-11', 'amount' => 150],
            ['category_id' => $food->id, 'date' => $this->month.'-11', 'amount' => 500],
        ]);

        // Different month — must not bleed into the total.
        $this->claimForOtherMonth($employee, $travel->id);

        $summary = app(ExpenseMonthlySummaryService::class)->summary($this->month, $hr);

        $row = collect($summary['rows'])->firstWhere('employee_id', $employee->id);

        $this->assertSame(350.0, $row['by_category'][$travel->id]);
        $this->assertSame(500.0, $row['by_category'][$food->id]);
        $this->assertSame(850.0, $row['total']);
        $this->assertSame(350.0, $summary['totals'][$travel->id]);
        $this->assertSame(850.0, $summary['grand_total']);
    }

    private function claimForOtherMonth(Employee $employee, int $categoryId): void
    {
        $other = Carbon::parse($this->month.'-01')->subMonth();

        $claim = ExpenseClaim::create([
            'claim_number' => 'EXP-OLD-'.uniqid(),
            'employee_id' => $employee->id,
            'claim_date' => $other,
            'status' => ExpenseClaim::STATUS_APPROVED,
        ]);

        ExpenseClaimLine::create([
            'expense_claim_id' => $claim->id,
            'category_id' => $categoryId,
            'expense_date' => $other->copy()->day(10)->toDateString(),
            'requested_amount' => 9999,
            'approved_amount' => 9999,
        ]);
    }

    public function test_day_wise_is_scoped_to_the_requesting_user(): void
    {
        $manager = $this->makeUser('MGR800', 'Manager');
        $report = $this->makeUser('EMP810', 'Employee', $manager->employee_id)->employee;
        $stranger = $this->makeUser('EMP820', 'Employee')->employee;

        $taxi = ExpenseCategory::where('code', 'TAXI')->firstOrFail();
        $this->claimFor($report, [['category_id' => $taxi->id, 'date' => $this->month.'-05', 'amount' => 300]]);
        $this->claimFor($stranger, [['category_id' => $taxi->id, 'date' => $this->month.'-05', 'amount' => 400]]);

        $service = app(ExpenseMonthlySummaryService::class);

        $this->assertCount(1, $service->dayWise($this->month, $report->id, $manager));
        $this->assertTrue($service->dayWise($this->month, $stranger->id, $manager)->isEmpty());
    }

    public function test_page_and_exports_respond(): void
    {
        $hr = $this->makeUser('SA002', 'Super Admin');
        $employee = $this->makeUser('EMP830', 'Employee')->employee;
        $misc = ExpenseCategory::where('code', 'MISC')->firstOrFail();
        $this->claimFor($employee, [['category_id' => $misc->id, 'date' => $this->month.'-07', 'amount' => 250]]);

        $this->actingAs($hr, 'web');

        $this->get('/admin/expense-monthly-summary')->assertStatus(200);

        $summary = $this->get('/reports/expense_monthly_summary/download?month='.$this->month);
        $summary->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $summary->headers->get('content-type'));

        $dayWise = $this->get('/reports/expense_daywise/download?month='.$this->month.'&employee='.$employee->id);
        $dayWise->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $dayWise->headers->get('content-type'));
    }

    public function test_plain_employee_cannot_reach_the_summary_download(): void
    {
        $employee = $this->makeUser('EMP840', 'Employee');
        $this->actingAs($employee, 'web');

        $this->get('/reports/expense_monthly_summary/download?month='.$this->month)->assertStatus(403);
    }
}
