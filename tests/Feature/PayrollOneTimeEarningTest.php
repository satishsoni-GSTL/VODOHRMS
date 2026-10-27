<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollInput;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\SalaryStructureService;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollOneTimeEarningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
        $this->seed(Phase4Seeder::class);
        $this->seed(\Database\Seeders\Phase5Seeder::class);
    }

    private function makeEmployee(string $code, Company $company): Employee
    {
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

        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        app(SalaryStructureService::class)->assign($employee, now()->subMonths(3)->startOfMonth()->toDateString(), 240000, [$basic->id => 20000]);

        return $employee;
    }

    public function test_bonus_is_added_to_selected_employees_for_that_month_only(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $chosen = $this->makeEmployee('BON100', $company);
        $other = $this->makeEmployee('BON101', $company);
        $month = now()->subMonth()->format('Y-m');

        $run = PayrollRun::create(['payroll_month' => $month, 'company_id' => $company->id, 'status' => PayrollRun::STATUS_DRAFT]);
        $calc = app(PayrollCalculationService::class);
        $calc->calculate($run);

        $grossBefore = (float) $run->employees()->where('employee_id', $chosen->id)->value('gross_earnings');
        $otherGrossBefore = (float) $run->employees()->where('employee_id', $other->id)->value('gross_earnings');

        $count = $calc->addOneTimeEarning($run->fresh(), [$chosen], PayrollInput::TYPE_BONUS, 5000, 'Diwali bonus', $chosen->user);
        $this->assertSame(1, $count);

        $after = $run->employees()->where('employee_id', $chosen->id)->firstOrFail();
        $this->assertEqualsWithDelta($grossBefore + 5000, (float) $after->gross_earnings, 0.01);
        $this->assertEqualsWithDelta(5000.0, (float) $after->lines()->where('label', 'Bonus')->where('component_type', SalaryComponent::TYPE_EARNING)->sum('amount'), 0.01);
        $this->assertEqualsWithDelta($otherGrossBefore, (float) $run->employees()->where('employee_id', $other->id)->value('gross_earnings'), 0.01);

        // A full recalculation keeps it; the next month's run does not get it.
        $calc->calculate($run->fresh());
        $this->assertSame(1, $run->employees()->where('employee_id', $chosen->id)->firstOrFail()->lines()->where('label', 'Bonus')->count());

        $nextRun = PayrollRun::create(['payroll_month' => now()->format('Y-m'), 'company_id' => $company->id, 'status' => PayrollRun::STATUS_DRAFT]);
        $calc->calculate($nextRun);
        $this->assertSame(0, $nextRun->employees()->where('employee_id', $chosen->id)->firstOrFail()->lines()->where('label', 'Bonus')->count());
    }

    public function test_cannot_add_bonus_to_a_finalized_run(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $employee = $this->makeEmployee('BON200', $company);
        $run = PayrollRun::create(['payroll_month' => now()->subMonth()->format('Y-m'), 'company_id' => $company->id, 'status' => PayrollRun::STATUS_FINALIZED]);

        $this->expectException(ValidationException::class);
        app(PayrollCalculationService::class)->addOneTimeEarning($run, [$employee], PayrollInput::TYPE_INCENTIVE, 1000, 'Target', $employee->user);
    }
}
