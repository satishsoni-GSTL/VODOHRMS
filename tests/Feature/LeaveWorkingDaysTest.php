<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveApplicationService;
use App\Services\LeaveBalanceService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveWorkingDaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        Carbon::setTestNow(Carbon::parse('2026-03-02')); // a Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(array $weeklyOff): Employee
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => 'EMP-'.uniqid(),
            'first_name' => 'Test',
            'company_id' => $company->id,
            'date_of_joining' => now()->subYear(),
            'status' => Employee::STATUS_ACTIVE,
            'weekly_off' => $weeklyOff,
        ]);

        User::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => 'Test',
            'email' => strtolower($employee->employee_code).'@vodohrms.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);

        return $employee;
    }

    public function test_weekly_off_days_inside_the_range_do_not_consume_leave(): void
    {
        $employee = $this->employee(['saturday', 'sunday']);
        $cl = LeaveType::where('code', 'EL')->firstOrFail();
        app(LeaveBalanceService::class)->credit($employee, $cl, now()->year, 20, 'Credit');

        // Thu 2026-03-05 .. Wed 2026-03-11 = 7 calendar days, minus Sat 7th + Sun 8th = 5.
        $application = app(LeaveApplicationService::class)->apply(
            $employee, $cl, Carbon::parse('2026-03-05'), Carbon::parse('2026-03-11'),
            false, null, 'Trip', null,
        );

        $this->assertSame(5.0, (float) $application->days);
    }

    public function test_a_company_holiday_inside_the_range_does_not_consume_leave(): void
    {
        $employee = $this->employee(['sunday']);
        $cl = LeaveType::where('code', 'EL')->firstOrFail();
        app(LeaveBalanceService::class)->credit($employee, $cl, now()->year, 20, 'Credit');

        Holiday::create(['name' => 'Holi', 'date' => '2026-03-04', 'type' => 'public', 'company_id' => $employee->company_id]);

        // Mon 2026-03-02 .. Fri 2026-03-06 = 5 days, minus the Wed holiday = 4.
        $application = app(LeaveApplicationService::class)->apply(
            $employee, $cl, Carbon::parse('2026-03-02'), Carbon::parse('2026-03-06'),
            false, null, 'Personal', null,
        );

        $this->assertSame(4.0, (float) $application->days);
    }

    public function test_an_all_weekly_off_range_is_rejected(): void
    {
        $employee = $this->employee(['saturday', 'sunday']);
        $cl = LeaveType::where('code', 'EL')->firstOrFail();
        app(LeaveBalanceService::class)->credit($employee, $cl, now()->year, 20, 'Credit');

        $this->expectException(ValidationException::class);

        app(LeaveApplicationService::class)->apply(
            $employee, $cl, Carbon::parse('2026-03-07'), Carbon::parse('2026-03-08'), // Sat + Sun
            false, null, 'Weekend', null,
        );
    }
}
