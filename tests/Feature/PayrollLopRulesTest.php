<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\SalaryStructureService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollLopRulesTest extends TestCase
{
    use RefreshDatabase;

    private string $payrollMonth;

    private Carbon $monthStart;

    private Carbon $monthEnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
        $this->seed(Phase4Seeder::class);

        $this->payrollMonth = now()->subMonth()->format('Y-m');
        $this->monthStart = Carbon::createFromFormat('Y-m', $this->payrollMonth)->startOfMonth();
        $this->monthEnd = $this->monthStart->copy()->endOfMonth();
    }

    private function makeEmployee(string $code, ?array $weeklyOff): Employee
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now()->subYears(2),
            'status' => Employee::STATUS_ACTIVE,
            'weekly_off' => $weeklyOff,
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
        app(SalaryStructureService::class)->assign(
            $employee, $this->monthStart->copy()->subYear()->toDateString(), 300000, [$basic->id => 20000],
        );

        return $employee;
    }

    private function lopDaysFor(Employee $employee): float
    {
        $service = app(PayrollCalculationService::class);
        $run = $service->getOrCreateRun($this->payrollMonth, $employee->company_id);
        $service->calculate($run);

        return (float) $run->employees()->where('employee_id', $employee->id)->firstOrFail()->lop_days;
    }

    public function test_saturday_and_sunday_are_not_lop_when_no_weekly_off_is_configured(): void
    {
        $employee = $this->makeEmployee('EMP600', null);

        // Present every Mon-Fri; nothing at all on Sat/Sun.
        foreach (CarbonPeriod::create($this->monthStart, $this->monthEnd) as $date) {
            if ($date->isWeekend()) {
                continue;
            }

            Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => $date->toDateString(),
                'status' => Attendance::STATUS_PRESENT,
                'source' => 'manual',
            ]);
        }

        $this->assertSame(0.0, $this->lopDaysFor($employee));
    }

    public function test_a_single_punch_day_is_not_docked_as_lop(): void
    {
        $employee = $this->makeEmployee('EMP601', ['saturday', 'sunday']);

        $workdays = collect(CarbonPeriod::create($this->monthStart, $this->monthEnd))
            ->reject(fn (Carbon $d) => $d->isWeekend())
            ->values();

        foreach ($workdays as $date) {
            Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => $date->toDateString(),
                'status' => Attendance::STATUS_PRESENT,
                'source' => 'manual',
            ]);
        }

        // One day: forgot to punch out — status slid to "absent" but there is a punch on record.
        Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $workdays->first()->toDateString())
            ->update(['status' => Attendance::STATUS_ABSENT, 'first_in' => '09:12:00']);

        // Another day: a genuine no-show, no punch at all.
        Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $workdays->get(1)->toDateString())
            ->update(['status' => Attendance::STATUS_ABSENT]);

        $this->assertSame(1.0, $this->lopDaysFor($employee));
    }
}
