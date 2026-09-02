<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\SalaryStructureService;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryStructureManualDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
        $this->seed(Phase4Seeder::class);
    }

    private function makeEmployee(string $code): Employee
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now()->subYears(2),
            'status' => Employee::STATUS_ACTIVE,
        ]);

        User::create([
            'employee_id' => $employee->id,
            'employee_code' => $code,
            'name' => $code,
            'email' => $employee->official_email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        return $employee;
    }

    private function customDeductionComponent(): SalaryComponent
    {
        return SalaryComponent::firstOrCreate(
            ['code' => 'LOAN_RECOVERY'],
            [
                'name' => 'Loan Recovery',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'calculation_type' => SalaryComponent::CALC_FIXED,
                'show_on_payslip' => true,
                'sequence' => 30,
                'is_active' => true,
            ],
        );
    }

    public function test_a_manual_deduction_line_is_persisted_and_reduces_net_pay(): void
    {
        $employee = $this->makeEmployee('EMP500');
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $recovery = $this->customDeductionComponent();

        $structure = app(SalaryStructureService::class)->assign(
            $employee,
            now()->subMonths(2)->startOfMonth()->toDateString(),
            360000,
            [$basic->id => 20000],
            null,
            'With a recovery',
            [$recovery->id => 1500],
        );

        $lines = $structure->lines()->with('component')->get()->keyBy(fn ($l) => $l->component->code);

        $this->assertEqualsWithDelta(1500.0, (float) $lines['LOAN_RECOVERY']->monthly_amount, 0.01);
        $this->assertEqualsWithDelta(18000.0, (float) $lines['LOAN_RECOVERY']->annual_amount, 0.01);
        // Auto-computed statutory deductions still apply alongside the manual one.
        $this->assertArrayHasKey('PF_EMPLOYEE', $lines->all());
    }

    public function test_a_manual_amount_overrides_the_auto_computed_one_for_the_same_component(): void
    {
        $employee = $this->makeEmployee('EMP501');
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $pf = SalaryComponent::where('code', 'PF_EMPLOYEE')->firstOrFail();

        $structure = app(SalaryStructureService::class)->assign(
            $employee,
            now()->subMonths(2)->startOfMonth()->toDateString(),
            360000,
            [$basic->id => 20000],
            null,
            null,
            [$pf->id => 1800], // hand-set, not the 12%-of-20000 = 2400 the auto path would produce
        );

        $pfLines = $structure->lines()->where('salary_component_id', $pf->id)->get();

        $this->assertCount(1, $pfLines);
        $this->assertEqualsWithDelta(1800.0, (float) $pfLines->first()->monthly_amount, 0.01);
    }

    public function test_zero_and_non_deduction_components_are_ignored(): void
    {
        $employee = $this->makeEmployee('EMP502');
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $hra = SalaryComponent::where('code', 'HRA')->firstOrFail(); // earning, not a deduction
        $recovery = $this->customDeductionComponent();

        $structure = app(SalaryStructureService::class)->assign(
            $employee,
            now()->subMonths(2)->startOfMonth()->toDateString(),
            360000,
            [$basic->id => 20000],
            null,
            null,
            [$recovery->id => 0, $hra->id => 999],
        );

        // Zero amount -> no line. HRA is an earning, so passing it in the deduction map does nothing.
        $this->assertSame(0, $structure->lines()->where('salary_component_id', $recovery->id)->count());
        $this->assertSame(0, $structure->lines()->where('salary_component_id', $hra->id)->count());
    }
}
