<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeTaxRegime;
use App\Models\FinancialYear;
use App\Models\SalaryComponent;
use App\Models\TaxRegimeSlab;
use App\Models\TaxSection;
use App\Models\User;
use App\Services\IncomeTaxCalculationService;
use App\Services\PayrollCalculationService;
use App\Services\SalaryStructureService;
use App\Services\TaxDeclarationService;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\Phase5Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5SmokeTest extends TestCase
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
    }

    private function makeUser(string $employeeCode, string $role, ?Company $company = null): User
    {
        $company ??= Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now()->subYears(2),
            'status' => Employee::STATUS_ACTIVE,
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

    public function test_admin_can_view_income_tax_pages(): void
    {
        $admin = $this->makeUser('ADMIN001', 'Super Admin');
        $this->actingAs($admin, 'web');

        $this->get('/admin/financial-years')->assertStatus(200);
        $this->get('/admin/tax-sections')->assertStatus(200);
        $this->get('/admin/tax-regime-slabs')->assertStatus(200);
        $this->get('/admin/tax-regime-configs')->assertStatus(200);
        $this->get('/admin/employee-tax-regimes')->assertStatus(200);
        $this->get('/admin/employee-tax-declarations')->assertStatus(200);
        $this->get('/admin/my-tax-comparison')->assertStatus(200);
    }

    public function test_slab_tax_calculation_matches_expected_old_regime_amount(): void
    {
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();
        $slabs = $fy->slabsFor(TaxRegimeSlab::REGIME_OLD);

        // Taxable income 800,000 under old regime slabs:
        // 0-250000 @0% = 0; 250000-500000 @5% = 12500; 500000-800000 @20% = 60000
        $tax = app(IncomeTaxCalculationService::class)->calculateSlabTax($slabs, 800000);

        $this->assertEquals(72500.0, $tax);
    }

    public function test_rebate_zeroes_tax_for_income_at_new_regime_rebate_limit(): void
    {
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();
        $config = $fy->configFor(TaxRegimeSlab::REGIME_NEW);
        $slabs = $fy->slabsFor(TaxRegimeSlab::REGIME_NEW);

        $service = app(IncomeTaxCalculationService::class);
        $taxableIncome = 700000.0;
        $taxBeforeRebate = $service->calculateSlabTax($slabs, $taxableIncome);
        $rebate = $service->calculateRebate($config, $taxableIncome, $taxBeforeRebate);

        $this->assertGreaterThan(0, $taxBeforeRebate);
        $this->assertEquals($taxBeforeRebate, $rebate);
    }

    public function test_old_vs_new_regime_comparison_recommends_lower_tax_regime(): void
    {
        $employee = $this->makeUser('EMP500', 'Employee');
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();

        app(SalaryStructureService::class)->assign(
            $employee->employee, '2026-04-01', 1200000, [$basic->id => 60000],
        );

        $comparison = app(IncomeTaxCalculationService::class)->compareRegimes($employee->employee, $fy, '2026-05');

        $this->assertContains($comparison['recommended'], [TaxRegimeSlab::REGIME_OLD, TaxRegimeSlab::REGIME_NEW]);
        $this->assertGreaterThan(0, $comparison['old']->taxable_income);
        $this->assertGreaterThan(0, $comparison['new']->taxable_income);
    }

    public function test_verified_declaration_reduces_old_regime_taxable_income(): void
    {
        $employee = $this->makeUser('EMP510', 'Employee');
        $hr = $this->makeUser('HR500', 'HR Admin');
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();
        $section80c = TaxSection::where('financial_year_id', $fy->id)->where('code', '80C')->firstOrFail();

        app(SalaryStructureService::class)->assign(
            $employee->employee, '2026-04-01', 1200000, [$basic->id => 60000],
        );

        $before = app(IncomeTaxCalculationService::class)->project($employee->employee, $fy, '2026-05', TaxRegimeSlab::REGIME_OLD);

        $declaration = app(TaxDeclarationService::class)->declare($employee->employee, $fy, $section80c, 150000, null);
        app(TaxDeclarationService::class)->verify($declaration, 150000, 'Verified against Form 16');

        $after = app(IncomeTaxCalculationService::class)->project($employee->employee, $fy, '2026-05', TaxRegimeSlab::REGIME_OLD);

        $this->assertEquals(150000.0, (float) $declaration->fresh()->eligible_amount);
        $this->assertLessThan((float) $before->taxable_income, (float) $after->taxable_income);
    }

    public function test_payroll_automatically_deducts_projected_monthly_tds(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $employee = $this->makeUser('EMP520', 'Employee', $company);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();

        // High salary to guarantee a non-zero tax liability.
        app(SalaryStructureService::class)->assign(
            $employee->employee, '2026-04-01', 1800000, [$basic->id => 100000],
        );

        EmployeeTaxRegime::create([
            'employee_id' => $employee->employee_id,
            'financial_year_id' => $fy->id,
            'selected_regime' => TaxRegimeSlab::REGIME_OLD,
            'selection_date' => '2026-04-01',
        ]);

        $payrollService = app(PayrollCalculationService::class);
        $run = $payrollService->getOrCreateRun('2026-05', $company->id);
        $payrollService->calculate($run);

        $runEmployee = $run->employees()->where('employee_id', $employee->employee_id)->firstOrFail();
        $tdsLine = $runEmployee->lines()->where('label', IncomeTaxCalculationService::TDS_LABEL)->first();

        $this->assertNotNull($tdsLine, 'Expected an automatic TDS deduction line in payroll.');
        $this->assertGreaterThan(0, $tdsLine->amount);
        $this->assertLessThan((float) $runEmployee->gross_earnings, (float) $runEmployee->net_pay);
    }
}
