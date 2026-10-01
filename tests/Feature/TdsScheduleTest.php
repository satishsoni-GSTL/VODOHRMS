<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeTdsSchedule;
use App\Models\FinancialYear;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\IncomeTaxCalculationService;
use App\Services\PayrollCalculationService;
use App\Services\SalaryStructureService;
use App\Services\TdsScheduleService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\Phase5Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TdsScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private FinancialYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        // Mid-year go-live: HRMS starts running payroll from Oct 2026 in FY 2026-27.
        Carbon::setTestNow(Carbon::parse('2026-10-15'));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
        $this->seed(Phase4Seeder::class);
        $this->seed(Phase5Seeder::class);

        $this->company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $this->year = FinancialYear::where('name', '2026-27')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeEmployee(string $code): Employee
    {
        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $this->company->id,
            'date_of_joining' => '2024-01-01',
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
        app(SalaryStructureService::class)->assign($employee, '2026-04-01', 2400000, [$basic->id => 100000]);

        return $employee->fresh();
    }

    private function amounts(Employee $employee): array
    {
        return EmployeeTdsSchedule::where('employee_id', $employee->id)
            ->orderBy('payroll_month')
            ->pluck('amount', 'payroll_month')
            ->map(fn ($a) => (float) $a)
            ->all();
    }

    public function test_generate_creates_twelve_months_and_spreads_annual_tax_from_the_start_month(): void
    {
        $employee = $this->makeEmployee('TDS100');

        $result = app(TdsScheduleService::class)->generate($employee, $this->year, '2026-10');
        $amounts = $this->amounts($employee);

        $this->assertGreaterThan(0, $result['annual_tax']);
        $this->assertCount(12, $amounts);

        foreach (['2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09'] as $preHrms) {
            $this->assertSame(0.0, $amounts[$preHrms]);
        }

        $this->assertEqualsWithDelta($result['annual_tax'], array_sum($amounts), 0.01);
        $this->assertEquals($amounts['2026-10'], $amounts['2026-11']);
    }

    public function test_tds_deducted_before_go_live_is_kept_and_the_balance_is_respread(): void
    {
        $employee = $this->makeEmployee('TDS101');
        $service = app(TdsScheduleService::class);

        $result = $service->generate($employee, $this->year, '2026-10');

        // HR enters what the legacy payroll already withheld for Apr–Sep.
        EmployeeTdsSchedule::where('employee_id', $employee->id)
            ->where('payroll_month', '<', '2026-10')
            ->get()
            ->each(fn ($row) => $service->updateAmount($row, 5000));

        $service->generate($employee, $this->year, '2026-10');
        $amounts = $this->amounts($employee);

        $this->assertSame(5000.0, $amounts['2026-04']);
        $this->assertEqualsWithDelta($result['annual_tax'], array_sum($amounts), 0.01);
        $this->assertEqualsWithDelta(
            $result['annual_tax'] - 30000,
            array_sum(array_filter($amounts, fn ($m) => $m >= '2026-10', ARRAY_FILTER_USE_KEY)),
            0.01,
        );
    }

    public function test_regenerate_keeps_manual_edits_unless_overwriting(): void
    {
        $employee = $this->makeEmployee('TDS102');
        $service = app(TdsScheduleService::class);
        $service->generate($employee, $this->year, '2026-10');

        $row = EmployeeTdsSchedule::where('employee_id', $employee->id)->where('payroll_month', '2026-12')->firstOrFail();
        $service->updateAmount($row, 1234);

        $service->generate($employee, $this->year, '2026-10');
        $this->assertSame(1234.0, $this->amounts($employee)['2026-12']);

        $service->generate($employee, $this->year, '2026-10', overwriteManual: true);
        $this->assertNotSame(1234.0, $this->amounts($employee)['2026-12']);
        $this->assertFalse($row->fresh()->is_manual);
    }

    public function test_payroll_deducts_the_last_saved_tds_and_locks_the_month_once_finalized(): void
    {
        $employee = $this->makeEmployee('TDS103');
        $service = app(TdsScheduleService::class);
        $service->generate($employee, $this->year, '2026-10');

        $row = EmployeeTdsSchedule::where('employee_id', $employee->id)->where('payroll_month', '2026-10')->firstOrFail();
        $service->updateAmount($row, 4321.5);

        $run = PayrollRun::create(['payroll_month' => '2026-10', 'company_id' => $this->company->id, 'status' => PayrollRun::STATUS_DRAFT]);
        $calc = app(PayrollCalculationService::class);
        $calc->calculate($run);

        $runEmployee = $run->employees()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(4321.5, (float) $runEmployee->lines()->where('label', IncomeTaxCalculationService::TDS_LABEL)->sum('amount'), 0.01);

        $calc->finalize($run->fresh());
        $this->assertTrue($service->isLocked($row->fresh()));

        $this->expectException(ValidationException::class);
        $service->updateAmount($row->fresh(), 100);
    }

    public function test_without_a_schedule_payroll_falls_back_to_auto_calculated_tds(): void
    {
        $employee = $this->makeEmployee('TDS104');

        // Schedule a different employee so a schedule exists for the year, just not for this one.
        app(TdsScheduleService::class)->generate($this->makeEmployee('TDS106'), $this->year, '2026-10');

        $incomeTax = app(IncomeTaxCalculationService::class);
        $tds = $incomeTax->monthlyTdsForPayroll($employee, $this->year, '2026-10');
        $projection = $incomeTax->project($employee, $this->year, '2026-10', 'old');

        $this->assertEqualsWithDelta((float) $projection->projected_monthly_tds, $tds, 0.01);
        $this->assertSame(0, EmployeeTdsSchedule::where('employee_id', $employee->id)->count());
    }

    public function test_tds_schedule_page_loads_for_admin(): void
    {
        $employee = $this->makeEmployee('TDS105');
        app(TdsScheduleService::class)->generate($employee, $this->year, '2026-10');
        $employee->user->assignRole('Super Admin');

        $this->actingAs($employee->user, 'web')
            ->get('/admin/employee-tds-schedules')
            ->assertStatus(200)
            ->assertSee('TDS105');
    }
}
