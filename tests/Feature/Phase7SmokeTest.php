<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\FinancialYear;
use App\Models\Form16;
use App\Models\SalaryComponent;
use App\Models\TaxRegimeConfig;
use App\Models\TaxRegimeSlab;
use App\Models\User;
use App\Services\Form16Service;
use App\Services\PayrollCalculationService;
use App\Services\SalaryStructureService;
use App\Services\TaxRegimeSelectionService;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\Phase5Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Phase7SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
        $this->seed(Phase4Seeder::class);
        $this->seed(Phase5Seeder::class);
    }

    private function makeUser(string $employeeCode, string $role, ?Company $company = null): User
    {
        $company ??= Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'pan' => 'AAACH1234A', 'tan' => 'DELH12345A', 'is_active' => true]);

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

    public function test_employee_can_select_own_regime_when_unlocked(): void
    {
        $employee = $this->makeUser('EMP700', 'Employee');
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();

        $regime = app(TaxRegimeSelectionService::class)->select(
            $employee->employee, $fy, TaxRegimeSlab::REGIME_NEW, $employee
        );

        $this->assertEquals(TaxRegimeSlab::REGIME_NEW, $regime->selected_regime);
        $this->assertNull($regime->lock_date);
        $this->assertFalse(app(TaxRegimeSelectionService::class)->isLocked($regime));
    }

    public function test_locking_blocks_the_employee_from_self_changing_their_regime(): void
    {
        $employee = $this->makeUser('EMP701', 'Employee');
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();
        $service = app(TaxRegimeSelectionService::class);

        $regime = $service->select($employee->employee, $fy, TaxRegimeSlab::REGIME_OLD, $employee);
        $service->lock($regime);

        $this->assertTrue($service->isLocked($regime->fresh()));

        $this->expectException(ValidationException::class);
        $service->select($employee->employee, $fy, TaxRegimeSlab::REGIME_NEW, $employee);
    }

    public function test_admin_can_fix_a_locked_employee_regime(): void
    {
        $employee = $this->makeUser('EMP702', 'Employee');
        $hr = $this->makeUser('HR700', 'HR Admin');
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();
        $service = app(TaxRegimeSelectionService::class);

        $regime = $service->select($employee->employee, $fy, TaxRegimeSlab::REGIME_OLD, $employee);
        $service->lock($regime);

        // HR (tax.manage) fixes the regime to New despite the lock.
        $fixed = $service->select($employee->employee, $fy, TaxRegimeSlab::REGIME_NEW, $hr);

        $this->assertEquals(TaxRegimeSlab::REGIME_NEW, $fixed->selected_regime);
        $this->assertEquals($hr->id, $fixed->changed_by);
        $this->assertEquals(TaxRegimeSlab::REGIME_NEW, $employee->employee->fresh()->selectedRegimeFor($fy));
    }

    public function test_unlocking_restores_the_employees_ability_to_self_change(): void
    {
        $employee = $this->makeUser('EMP703', 'Employee');
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();
        $service = app(TaxRegimeSelectionService::class);

        $regime = $service->select($employee->employee, $fy, TaxRegimeSlab::REGIME_OLD, $employee);
        $service->lock($regime);
        $service->unlock($regime->fresh());

        $changed = $service->select($employee->employee, $fy, TaxRegimeSlab::REGIME_NEW, $employee);

        $this->assertEquals(TaxRegimeSlab::REGIME_NEW, $changed->selected_regime);
    }

    public function test_regime_change_allowed_false_blocks_changes_program_wide_even_without_a_row_lock(): void
    {
        $employee = $this->makeUser('EMP704', 'Employee');
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();
        $service = app(TaxRegimeSelectionService::class);

        $regime = $service->select($employee->employee, $fy, TaxRegimeSlab::REGIME_OLD, $employee);
        $this->assertNull($regime->lock_date);

        TaxRegimeConfig::where('financial_year_id', $fy->id)
            ->where('regime', TaxRegimeSlab::REGIME_OLD)
            ->update(['regime_change_allowed' => false]);

        $this->assertTrue($service->isLocked($regime->fresh(), $fy));

        $this->expectException(ValidationException::class);
        $service->select($employee->employee, $fy, TaxRegimeSlab::REGIME_NEW, $employee);
    }

    public function test_form16_generates_a_downloadable_pdf_with_computed_figures(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'pan' => 'AAACH1234A', 'tan' => 'DELH12345A', 'is_active' => true]);
        $employee = $this->makeUser('EMP710', 'Employee', $company);
        $hr = $this->makeUser('HR710', 'HR Admin', $company);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();

        app(SalaryStructureService::class)->assign(
            $employee->employee, '2026-04-01', 1200000, [$basic->id => 60000],
        );

        app(TaxRegimeSelectionService::class)->select($employee->employee, $fy, TaxRegimeSlab::REGIME_OLD, $employee);

        $payrollService = app(PayrollCalculationService::class);
        $run = $payrollService->getOrCreateRun('2026-04', $company->id);
        $payrollService->calculate($run);

        $form16 = app(Form16Service::class)->generate($employee->employee, $fy, $hr);

        $this->assertInstanceOf(Form16::class, $form16);
        $this->assertEquals(TaxRegimeSlab::REGIME_OLD, $form16->regime);
        $this->assertEquals($hr->id, $form16->generated_by);
        Storage::disk('local')->assertExists($form16->pdf_path);

        // Employee can download their own Form 16.
        $this->actingAs($employee, 'web')
            ->get(route('form16.download', $form16))
            ->assertStatus(200);
    }

    public function test_a_different_employee_without_tax_view_cannot_download_someone_elses_form16(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'pan' => 'AAACH1234A', 'tan' => 'DELH12345A', 'is_active' => true]);
        $employee = $this->makeUser('EMP720', 'Employee', $company);
        $otherEmployee = $this->makeUser('EMP721', 'Employee', $company);
        $hr = $this->makeUser('HR720', 'HR Admin', $company);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();

        app(SalaryStructureService::class)->assign(
            $employee->employee, '2026-04-01', 1200000, [$basic->id => 60000],
        );

        $payrollService = app(PayrollCalculationService::class);
        $run = $payrollService->getOrCreateRun('2026-04', $company->id);
        $payrollService->calculate($run);

        $form16 = app(Form16Service::class)->generate($employee->employee, $fy, $hr);

        $this->actingAs($otherEmployee, 'web')
            ->get(route('form16.download', $form16))
            ->assertStatus(403);
    }

    /**
     * Regression test: `full_name` is a PHP accessor (Employee::getFullNameAttribute()), not
     * a real employees column. The Generate Form16 page's employee table used to pass that
     * column name straight to ->searchable() with no explicit column list, so Filament tried
     * to search it as a raw SQL column and crashed with
     * "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'full_name'" the first time
     * anyone typed into the search box. Fixed by ->searchable(['first_name', 'last_name']).
     */
    public function test_generate_form16_employee_search_does_not_crash_on_the_full_name_column(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'pan' => 'AAACH1234A', 'tan' => 'DELH12345A', 'is_active' => true]);
        $tax = $this->makeUser('TAX730', 'Super Admin', $company);
        $this->makeUser('SAT730', 'Employee', $company); // first_name defaults to the employee_code in makeUser()

        $this->actingAs($tax, 'web');

        Livewire::test(\App\Filament\Pages\GenerateForm16::class)
            ->set('tableSearch', 'SAT730')
            ->assertSuccessful()
            ->assertSee('SAT730');
    }
}
