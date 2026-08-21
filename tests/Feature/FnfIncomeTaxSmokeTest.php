<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\FinancialYear;
use App\Models\FullFinalSettlement;
use App\Models\Resignation;
use App\Models\User;
use App\Services\IncomeTaxCalculationService;
use Carbon\Carbon;
use Database\Seeders\Phase5Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the fix for: a resigned employee's Form 16 / tax projection was silently based
 * only on what was formally run through monthly payroll ("credited") — completely missing
 * whatever they were actually paid through their Full & Final settlement instead (final
 * partial-month salary, bonus, other earnings, and the TDS withheld against it).
 */
class FnfIncomeTaxSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase5Seeder::class);
    }

    private function makeUser(string $employeeCode, string $role): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now(),
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

    public function test_projected_income_includes_a_paid_fnf_settlements_taxable_earnings_but_not_encashment_or_reimbursement(): void
    {
        $employee = $this->makeUser('FNF001', 'Employee')->employee;
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();

        $resignation = Resignation::create([
            'employee_id' => $employee->id,
            'resignation_date' => '2026-07-01',
            'reason' => 'Personal',
            'requested_last_working_date' => '2026-07-31',
            'approved_last_working_date' => '2026-07-31', // inside FY 2026-27
            'status' => Resignation::STATUS_HR_APPROVED,
        ]);

        FullFinalSettlement::create([
            'resignation_id' => $resignation->id,
            'employee_id' => $employee->id,
            'pending_salary' => 45000,
            'bonus_incentive' => 10000,
            'other_earnings' => 5000,
            'reimbursement' => 3000,       // expense repayment — must NOT count as income
            'leave_encashment' => 20000,   // has its own exemption rules — deliberately excluded
            'notice_recovery' => 0,
            'loan_recovery' => 0,
            'advance_recovery' => 0,
            'tds' => 4000,
            'other_deductions' => 0,
            'final_amount' => 79000,
            'status' => FullFinalSettlement::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $taxService = app(IncomeTaxCalculationService::class);
        $lastMonth = Carbon::parse($fy->end_date)->format('Y-m');

        $projectedIncome = $taxService->projectAnnualIncome($employee, $fy, $lastMonth);

        // No payroll runs exist at all here, so this isolates exactly the F&F contribution:
        // pending_salary + bonus_incentive + other_earnings, and nothing else.
        $this->assertEquals(60000.0, $projectedIncome);

        $projection = $taxService->project($employee, $fy, $lastMonth, 'old');
        $this->assertEquals(4000.0, (float) $projection->tds_deducted_till_date);
    }

    public function test_a_draft_unpaid_fnf_settlement_does_not_count_yet(): void
    {
        $employee = $this->makeUser('FNF002', 'Employee')->employee;
        $fy = FinancialYear::where('name', '2026-27')->firstOrFail();

        $resignation = Resignation::create([
            'employee_id' => $employee->id,
            'resignation_date' => '2026-07-01',
            'reason' => 'Personal',
            'requested_last_working_date' => '2026-07-31',
            'approved_last_working_date' => '2026-07-31',
            'status' => Resignation::STATUS_HR_APPROVED,
        ]);

        FullFinalSettlement::create([
            'resignation_id' => $resignation->id,
            'employee_id' => $employee->id,
            'pending_salary' => 45000,
            'bonus_incentive' => 0,
            'other_earnings' => 0,
            'reimbursement' => 0,
            'leave_encashment' => 0,
            'notice_recovery' => 0,
            'loan_recovery' => 0,
            'advance_recovery' => 0,
            'tds' => 0,
            'other_deductions' => 0,
            'final_amount' => 45000,
            'status' => FullFinalSettlement::STATUS_CALCULATED, // not yet paid
        ]);

        $taxService = app(IncomeTaxCalculationService::class);
        $lastMonth = Carbon::parse($fy->end_date)->format('Y-m');

        $this->assertEquals(0.0, $taxService->projectAnnualIncome($employee, $fy, $lastMonth));
    }
}
