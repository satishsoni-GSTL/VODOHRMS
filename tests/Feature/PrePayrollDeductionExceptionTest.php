<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollInput;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\PrePayrollDeductionService;
use App\Services\SalaryStructureService;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrePayrollDeductionExceptionTest extends TestCase
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

        return $employee;
    }

    public function test_a_granted_exception_removes_that_deduction_from_the_run_only(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $employee = $this->makeEmployee('EXC100', $company);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();

        $recovery = SalaryComponent::firstOrCreate(['code' => 'LOAN_RECOVERY'], [
            'name' => 'Loan Recovery',
            'type' => SalaryComponent::TYPE_DEDUCTION,
            'calculation_type' => SalaryComponent::CALC_FIXED,
            'is_prorated' => false,
            'show_on_payslip' => true,
            'sequence' => 30,
            'is_active' => true,
        ]);

        $month = now()->subMonth()->format('Y-m');

        $structure = app(SalaryStructureService::class)->assign(
            $employee,
            now()->subMonths(3)->startOfMonth()->toDateString(),
            360000,
            [$basic->id => 20000],
            null,
            null,
            [$recovery->id => 1500],
        );
        $recoveryLine = $structure->lines()->where('salary_component_id', $recovery->id)->firstOrFail();

        PayrollInput::create([
            'employee_id' => $employee->id,
            'payroll_month' => $month,
            'type' => PayrollInput::TYPE_ADDITIONAL_DEDUCTION,
            'amount' => 700,
            'reason' => 'Canteen',
        ]);

        $run = PayrollRun::create([
            'payroll_month' => $month,
            'company_id' => $company->id,
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        $calc = app(PayrollCalculationService::class);

        $calc->calculate($run);
        $before = $run->employees()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(1500.0, (float) $before->lines()->where('label', 'Loan Recovery')->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(700.0, (float) $before->lines()->where('label', 'Additional Deduction')->sum('amount'), 0.01);
        $deductionsWithBoth = (float) $before->total_deductions;

        // Waive the salary-structure recovery line and the ad-hoc payroll input.
        $service = app(PrePayrollDeductionService::class);
        $rows = $service->rows($run->fresh());
        $service->grantException(
            $run,
            $rows->firstWhere('source_id', $recoveryLine->id),
            $employee->user,
            'One-off waiver',
        );
        $inputRow = $rows->first(fn ($r) => $r['source_type'] === 'payroll_input');
        $service->grantException($run, $inputRow, $employee->user, 'Waived canteen');

        $calc->calculate($run->fresh());
        $after = $run->employees()->where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(0, $after->lines()->where('label', 'Loan Recovery')->count());
        $this->assertSame(0, $after->lines()->where('label', 'Additional Deduction')->count());
        $this->assertEqualsWithDelta($deductionsWithBoth - 2200.0, (float) $after->total_deductions, 0.01);

        // Underlying records are untouched — next month still has them.
        $this->assertDatabaseHas('employee_salary_structure_lines', ['id' => $recoveryLine->id]);
        $this->assertDatabaseHas('payroll_inputs', ['employee_id' => $employee->id, 'amount' => 700]);
    }

    public function test_lop_amount_is_zero_when_fully_paid_and_positive_with_lop(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();

        // Present-all employee: has attendance for every day → no LOP.
        $present = $this->makeEmployee('LOP200', $company);
        $present->update(['weekly_off' => ['Sunday']]);
        $absent = $this->makeEmployee('LOP201', $company);
        $absent->update(['weekly_off' => ['Sunday']]);

        $month = now()->subMonth()->format('Y-m');
        $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        foreach ([$present, $absent] as $emp) {
            app(SalaryStructureService::class)->assign(
                $emp,
                $start->copy()->subMonth()->toDateString(),
                360000,
                [$basic->id => 30000],
            );
        }

        foreach (\Carbon\CarbonPeriod::create($start, $start->copy()->endOfMonth()) as $date) {
            \App\Models\Attendance::create([
                'employee_id' => $present->id,
                'attendance_date' => $date->toDateString(),
                'status' => \App\Models\Attendance::STATUS_PRESENT,
                'first_in' => $date->copy()->setTime(9, 0),
                'last_out' => $date->copy()->setTime(18, 0),
            ]);
        }

        $run = PayrollRun::create([
            'payroll_month' => $month,
            'company_id' => $company->id,
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        app(PayrollCalculationService::class)->calculate($run);

        $presentRow = $run->employees()->where('employee_id', $present->id)->firstOrFail();
        $absentRow = $run->employees()->where('employee_id', $absent->id)->firstOrFail();

        $this->assertEqualsWithDelta(0.0, (float) $presentRow->lop_days, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $presentRow->lop_amount, 0.01);

        $this->assertGreaterThan(0, (float) $absentRow->lop_days);
        $this->assertGreaterThan(0, (float) $absentRow->lop_amount);
    }
}
