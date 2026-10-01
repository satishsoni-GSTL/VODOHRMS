<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OptionalHolidayClaim;
use App\Models\OptionalHolidayLimit;
use App\Models\User;
use App\Services\AttendanceMonthlySummaryService;
use App\Services\OptionalHolidayService;
use App\Services\WorkingDayService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OptionalHolidayTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-01'));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);

        $this->company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeEmployee(string $code, string $role = 'Employee'): Employee
    {
        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $this->company->id,
            'date_of_joining' => '2024-01-01',
            'status' => Employee::STATUS_ACTIVE,
            'weekly_off' => ['saturday', 'sunday'],
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $code,
            'name' => $code,
            'email' => $employee->official_email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $employee->fresh();
    }

    private function optionalHoliday(string $date, string $name = 'Optional Day'): Holiday
    {
        return Holiday::create(['name' => $name, 'date' => $date, 'type' => Holiday::TYPE_OPTIONAL]);
    }

    public function test_employee_limit_overrides_the_year_default(): void
    {
        $employee = $this->makeEmployee('OH100');
        $other = $this->makeEmployee('OH101');
        $service = app(OptionalHolidayService::class);

        $this->assertSame(0, $service->limitFor($employee, 2026));

        OptionalHolidayLimit::create(['year' => 2026, 'employee_id' => null, 'max_claims' => 2]);
        OptionalHolidayLimit::create(['year' => 2026, 'employee_id' => $employee->id, 'max_claims' => 4]);

        $this->assertSame(4, $service->limitFor($employee, 2026));
        $this->assertSame(2, $service->limitFor($other, 2026));
        $this->assertSame(0, $service->limitFor($other, 2027));
    }

    public function test_claims_are_capped_at_the_yearly_limit_and_can_be_reclaimed_after_cancel(): void
    {
        $employee = $this->makeEmployee('OH102');
        $service = app(OptionalHolidayService::class);
        OptionalHolidayLimit::create(['year' => 2026, 'employee_id' => null, 'max_claims' => 1]);

        $first = $this->optionalHoliday('2026-10-20', 'Diwali (optional)');
        $second = $this->optionalHoliday('2026-11-05', 'Guru Nanak (optional)');

        $claim = $service->claim($employee, $first);
        $this->assertSame(1, $service->usedFor($employee, 2026));

        try {
            $service->claim($employee, $second);
            $this->fail('Claim beyond the limit should be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('limit reached', collect($e->errors())->flatten()->first());
        }

        $service->cancel($claim);
        $this->assertSame(0, $service->usedFor($employee, 2026));

        $service->claim($employee, $second);
        $this->assertSame(1, $service->usedFor($employee, 2026));
        $this->assertSame(1, OptionalHolidayClaim::where('status', OptionalHolidayClaim::STATUS_CLAIMED)->count());
    }

    public function test_past_or_non_optional_holidays_cannot_be_claimed(): void
    {
        $employee = $this->makeEmployee('OH103');
        $service = app(OptionalHolidayService::class);
        OptionalHolidayLimit::create(['year' => 2026, 'employee_id' => null, 'max_claims' => 5]);

        $past = $this->optionalHoliday('2026-09-15');
        $national = Holiday::create(['name' => 'Gandhi Jayanti', 'date' => '2026-10-02', 'type' => 'national']);

        foreach ([$past, $national] as $holiday) {
            try {
                $service->claim($employee, $holiday);
                $this->fail('Claim should be rejected.');
            } catch (ValidationException) {
                $this->assertSame(0, OptionalHolidayClaim::count());
            }
        }
    }

    public function test_optional_holiday_is_a_working_day_unless_claimed(): void
    {
        $claimer = $this->makeEmployee('OH104');
        $other = $this->makeEmployee('OH105');
        OptionalHolidayLimit::create(['year' => 2026, 'employee_id' => null, 'max_claims' => 2]);

        $holiday = $this->optionalHoliday('2026-10-20'); // Tuesday
        app(OptionalHolidayService::class)->claim($claimer, $holiday);

        $from = Carbon::parse('2026-10-19');
        $to = Carbon::parse('2026-10-21');
        $workingDays = app(WorkingDayService::class);

        $this->assertNotContains('2026-10-20', $workingDays->between($claimer, $from, $to));
        $this->assertContains('2026-10-20', $workingDays->between($other, $from, $to));

        $summary = app(AttendanceMonthlySummaryService::class);
        $monthStart = Carbon::parse('2026-10-01');
        $monthEnd = Carbon::parse('2026-10-31');

        $this->assertSame(AttendanceMonthlySummaryService::CODE_HOLIDAY, $summary->buildForEmployee($claimer, $monthStart, $monthEnd)['2026-10-20']['code']);
        $this->assertNotSame(AttendanceMonthlySummaryService::CODE_HOLIDAY, $summary->buildForEmployee($other, $monthStart, $monthEnd)['2026-10-20']['code']);
    }

    public function test_employee_can_open_claims_but_not_limits(): void
    {
        $employee = $this->makeEmployee('OH106');

        $this->actingAs($employee->user, 'web');
        $this->get('/admin/optional-holiday-claims')->assertStatus(200);
        $this->get('/admin/optional-holiday-claims/create')->assertStatus(200);
        $this->get('/admin/optional-holiday-limits')->assertStatus(403);
    }

    public function test_admin_can_open_limits(): void
    {
        $admin = $this->makeEmployee('OH107', 'Super Admin');

        $this->actingAs($admin->user, 'web');
        $this->get('/admin/optional-holiday-limits')->assertStatus(200);
    }
}
