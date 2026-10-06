<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\PayrollRunEmployee;
use App\Models\SalaryComponent;
use App\Services\PayrollCalculationService;
use App\Services\SalaryStructureService;
use Carbon\CarbonPeriod;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * September 2026: the 1st is a Tuesday; Fri 11 is followed by the weekend of 12/13.
 */
class PayrollLwpAndLastWorkingDateTest extends TestCase
{
    use RefreshDatabase;

    private const MONTH = '2026-09';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
        $this->seed(Phase4Seeder::class);
    }

    private function makeEmployee(string $code, array $attributes = []): Employee
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create($attributes + [
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => '2024-01-01',
            'status' => Employee::STATUS_ACTIVE,
            'weekly_off' => ['saturday', 'sunday'],
        ]);

        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();
        app(SalaryStructureService::class)->assign($employee, '2025-01-01', 360000, [$basic->id => 30000]);

        return $employee;
    }

    private function presentOnWeekdays(Employee $employee, string $from, string $to): void
    {
        foreach (CarbonPeriod::create($from, $to) as $date) {
            if (! $date->isWeekend()) {
                Attendance::create([
                    'employee_id' => $employee->id,
                    'attendance_date' => $date->toDateString(),
                    'first_in' => '09:30:00',
                    'last_out' => '18:30:00',
                    'status' => Attendance::STATUS_PRESENT,
                ]);
            }
        }
    }

    private function calculate(Employee $employee): ?PayrollRunEmployee
    {
        $service = app(PayrollCalculationService::class);
        $run = $service->getOrCreateRun(self::MONTH, $employee->company_id);
        $service->calculate($run);

        return $run->employees()->where('employee_id', $employee->id)->first();
    }

    public function test_leave_without_pay_is_lop_not_paid(): void
    {
        $employee = $this->makeEmployee('LWP001');
        $this->presentOnWeekdays($employee, '2026-09-01', '2026-09-30');

        // Tue 15 – Wed 16 on approved Leave Without Pay.
        Attendance::where('employee_id', $employee->id)->whereIn('attendance_date', ['2026-09-15', '2026-09-16'])->delete();
        LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::where('code', 'LWP')->value('id'),
            'from_date' => '2026-09-15',
            'to_date' => '2026-09-16',
            'days' => 2,
            'status' => LeaveApplication::STATUS_APPROVED,
        ]);

        $row = $this->calculate($employee);

        $this->assertEquals(28, (float) $row->paid_days);
        $this->assertEquals(2, (float) $row->lop_days);
        $this->assertEqualsWithDelta(2000, (float) $row->lop_amount, 0.01); // 30000 × 2/30
    }

    public function test_lwp_is_unpaid_even_if_flagged_paid(): void
    {
        LeaveType::where('code', 'LWP')->update(['is_paid_leave' => true]);

        $this->assertFalse(LeaveType::where('code', 'LWP')->first()->isPaidForPayroll());
    }

    public function test_salary_paid_till_last_working_date_plus_following_weekly_off(): void
    {
        // Already exited, last working day Friday 11 Sep.
        $employee = $this->makeEmployee('LWD001', ['status' => Employee::STATUS_EXITED, 'last_working_date' => '2026-09-11']);
        $this->presentOnWeekdays($employee, '2026-09-01', '2026-09-11');

        $row = $this->calculate($employee);

        $this->assertNotNull($row, 'An employee who left mid-month must still be in that month\'s payroll.');
        // 1–11 Sep worked/weekly-off + Sat 12 & Sun 13 paid; 14–30 Sep not employed (unpaid, not LOP).
        $this->assertEquals(13, (float) $row->paid_days);
        $this->assertEquals(0, (float) $row->lop_days);
        $this->assertEquals(0, (float) $row->lop_amount);
        $this->assertEqualsWithDelta(13000, (float) $row->gross_earnings, 0.01); // 30000 × 13/30
    }

    public function test_absence_before_last_working_date_is_still_lop(): void
    {
        $employee = $this->makeEmployee('LWD002', ['status' => Employee::STATUS_NOTICE_PERIOD, 'last_working_date' => '2026-09-11']);
        $this->presentOnWeekdays($employee, '2026-09-01', '2026-09-10'); // absent Fri 11

        $row = $this->calculate($employee);

        $this->assertEquals(12, (float) $row->paid_days);
        $this->assertEquals(1, (float) $row->lop_days);
    }

    public function test_hr_approved_resignation_sets_and_resyncs_employee_last_working_date(): void
    {
        $employee = $this->makeEmployee('LWD004');

        $resignation = \App\Models\Resignation::create([
            'employee_id' => $employee->id,
            'resignation_date' => '2026-08-15',
            'requested_last_working_date' => '2026-09-14',
            'reason' => 'Relocating',
            'status' => \App\Models\Resignation::STATUS_PENDING,
        ]);
        $this->assertNull($employee->fresh()->last_working_date);

        $resignation->applyApprovalOutcome('approved');
        $this->assertSame('2026-09-14', $employee->fresh()->last_working_date->toDateString());

        // HR later moves the date.
        $resignation->update(['approved_last_working_date' => '2026-09-11']);
        $this->assertSame('2026-09-11', $employee->fresh()->last_working_date->toDateString());
    }

    public function test_employee_who_left_before_the_month_is_excluded(): void
    {
        $employee = $this->makeEmployee('LWD003', ['status' => Employee::STATUS_NOTICE_PERIOD, 'last_working_date' => '2026-08-31']);

        $this->assertNull($this->calculate($employee));
    }
}
