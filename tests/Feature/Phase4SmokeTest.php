<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\PayslipService;
use App\Services\SalaryStructureService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase4SmokeTest extends TestCase
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

    private function makeUser(string $employeeCode, string $role, ?Company $company = null): User
    {
        $company ??= Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now()->subYear(),
            'status' => Employee::STATUS_ACTIVE,
            'weekly_off' => ['Sunday'],
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

    public function test_admin_can_view_payroll_pages(): void
    {
        $admin = $this->makeUser('ADMIN001', 'Super Admin');
        $this->actingAs($admin, 'web');

        $this->get('/admin/salary-components')->assertStatus(200);
        $this->get('/admin/employee-salary-structures')->assertStatus(200);
        $this->get('/admin/employee-salary-structures/create')->assertStatus(200);
        $this->get('/admin/payroll-inputs')->assertStatus(200);
        $this->get('/admin/payroll-runs')->assertStatus(200);
    }

    public function test_salary_structure_assignment_auto_computes_statutory_components(): void
    {
        $payrollAdmin = $this->makeUser('PAY001', 'Payroll Admin');
        $employee = $this->makeUser('EMP400', 'Employee');
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $hra = SalaryComponent::where('code', 'HRA')->firstOrFail();

        $structure = app(SalaryStructureService::class)->assign(
            $employee->employee,
            now()->subMonths(2)->startOfMonth()->toDateString(),
            600000, // annual CTC
            [$basic->id => 25000, $hra->id => 10000],
            $payrollAdmin->id,
            'Initial structure',
        );

        $lines = $structure->lines()->with('component')->get()->keyBy(fn ($l) => $l->component->code);

        $this->assertEquals(25000, (float) $lines['BASIC']->monthly_amount);
        $this->assertEquals(3000, (float) $lines['PF_EMPLOYEE']->monthly_amount); // 12% of 25000
        $this->assertEquals(187.5, (float) $lines['ESIC_EMPLOYEE']->monthly_amount); // 0.75% of 25000
        $this->assertEquals(200, (float) $lines['PROFESSIONAL_TAX']->monthly_amount); // fixed
        $this->assertEquals(3000, (float) $lines['PF_EMPLOYER']->monthly_amount); // 12% employer
        $this->assertEquals(35000, (float) $structure->monthly_gross); // BASIC + HRA (gross components)
    }

    public function test_salary_structure_revision_never_overwrites_history(): void
    {
        $employee = $this->makeUser('EMP410', 'Employee');
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $service = app(SalaryStructureService::class);

        $first = $service->assign($employee->employee, '2026-01-01', 300000, [$basic->id => 20000]);
        $second = $service->assign($employee->employee, '2026-06-01', 360000, [$basic->id => 24000]);

        $first->refresh();
        $this->assertEquals('2026-05-31', $first->effective_to->toDateString());
        $this->assertFalse($first->is_active);
        $this->assertTrue($second->is_active);
        $this->assertEquals(300000, (float) $second->previous_ctc);
        $this->assertEquals(2, $employee->employee->salaryStructures()->count());
    }

    public function test_payroll_run_calculates_lop_and_prevents_duplicate_generation(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $employee = $this->makeUser('EMP420', 'Employee', $company);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();

        $payrollMonth = now()->subMonth()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m', $payrollMonth)->startOfMonth();

        app(SalaryStructureService::class)->assign(
            $employee->employee, $monthStart->copy()->subYear()->toDateString(), 300000, [$basic->id => 20000],
        );

        // Mark every weekday as present, then flip 2 of them to absent (LOP).
        $weekdays = collect(CarbonPeriod::create($monthStart, $monthStart->copy()->endOfMonth()))
            ->filter(fn ($d) => $d->dayOfWeekIso !== 7)
            ->values();

        foreach ($weekdays as $date) {
            Attendance::create([
                'employee_id' => $employee->employee_id,
                'attendance_date' => $date->toDateString(),
                'status' => Attendance::STATUS_PRESENT,
                'source' => 'manual',
            ]);
        }

        foreach ($weekdays->take(2) as $date) {
            Attendance::where('employee_id', $employee->employee_id)
                ->where('attendance_date', $date->toDateString())
                ->update(['status' => Attendance::STATUS_ABSENT]);
        }

        $service = app(PayrollCalculationService::class);
        $run = $service->getOrCreateRun($payrollMonth, $company->id);
        $service->calculate($run);

        $runEmployee = $run->employees()->where('employee_id', $employee->employee_id)->firstOrFail();
        $this->assertEquals(2.0, (float) $runEmployee->lop_days);
        $this->assertGreaterThan(0, $runEmployee->net_pay);

        // Recalculation before finalization must not create a duplicate row (unique constraint).
        $service->calculate($run);
        $this->assertEquals(1, $run->employees()->where('employee_id', $employee->employee_id)->count());
    }

    public function test_payroll_run_finalize_lock_reopen_lifecycle_and_payslip_generation(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $payrollAdmin = $this->makeUser('PAY002', 'Payroll Admin', $company);
        $employee = $this->makeUser('EMP430', 'Employee', $company);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();

        $payrollMonth = now()->subMonth()->format('Y-m');
        app(SalaryStructureService::class)->assign(
            $employee->employee, now()->subYears(2)->toDateString(), 300000, [$basic->id => 20000],
        );

        Attendance::create([
            'employee_id' => $employee->employee_id,
            'attendance_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'status' => Attendance::STATUS_PRESENT,
            'source' => 'manual',
        ]);

        $service = app(PayrollCalculationService::class);
        $run = $service->getOrCreateRun($payrollMonth, $company->id);
        $service->calculate($run);

        // Cannot finalize a draft/uncalculated-only run twice without recalculation guard issues.
        $service->finalize($run);
        $run->refresh();
        $this->assertEquals(PayrollRun::STATUS_FINALIZED, $run->status);

        // Attendance for the month should now be frozen.
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->employee_id,
            'is_frozen' => true,
        ]);

        // Cannot recalculate a finalized run.
        try {
            $service->calculate($run);
            $this->fail('Expected calculating a finalized run to throw.');
        } catch (ValidationException) {
            // expected
        }

        $service->lock($run, $payrollAdmin);
        $run->refresh();
        $this->assertEquals(PayrollRun::STATUS_LOCKED, $run->status);
        $this->assertNotNull($run->locked_at);

        $service->reopen($run, 'Correcting an LOP entry');
        $run->refresh();
        $this->assertEquals(PayrollRun::STATUS_REOPENED, $run->status);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->employee_id,
            'is_frozen' => false,
        ]);

        // Editable again after reopening.
        $service->calculate($run);
        $run->refresh();
        $this->assertEquals(PayrollRun::STATUS_CALCULATED, $run->status);
    }

    public function test_payslip_can_be_generated_after_finalization(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $employee = $this->makeUser('EMP440', 'Employee', $company);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();

        $payrollMonth = now()->subMonth()->format('Y-m');
        app(SalaryStructureService::class)->assign(
            $employee->employee, now()->subYears(2)->toDateString(), 300000, [$basic->id => 20000],
        );

        $service = app(PayrollCalculationService::class);
        $run = $service->getOrCreateRun($payrollMonth, $company->id);
        $service->calculate($run);
        $service->finalize($run);

        $runEmployee = $run->employees()->where('employee_id', $employee->employee_id)->firstOrFail();
        $payslip = app(PayslipService::class)->generate($runEmployee);

        $this->assertNotNull($payslip->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($payslip->pdf_path));
    }
}
