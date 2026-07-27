<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use App\Notifications\BirthdayNotification;
use App\Notifications\UpcomingHolidayNotification;
use App\Notifications\WorkAnniversaryNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HrReminderNotificationsSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    private function makeUser(string $employeeCode, int $companyId, array $employeeOverrides = []): User
    {
        $employee = Employee::create(array_merge([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $companyId,
            'date_of_joining' => now()->subYears(5),
            'status' => Employee::STATUS_ACTIVE,
        ], $employeeOverrides));

        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employeeCode,
            'email' => $employee->official_email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole('Employee');

        return $user->fresh();
    }

    public function test_birthday_reminder_notifies_celebrant_and_same_company_colleagues_only(): void
    {
        $companyA = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $companyB = Company::firstOrCreate(['code' => 'BR'], ['name' => 'Branch Office', 'is_active' => true]);

        $celebrant = $this->makeUser('BDAY1', $companyA->id, ['dob' => now()->subYears(25)->toDateString()]);
        $colleagueSameCompany = $this->makeUser('COLA1', $companyA->id);
        $colleagueOtherCompany = $this->makeUser('COLB1', $companyB->id);

        $this->artisan('hr:send-daily-reminders')->assertExitCode(0);

        Notification::assertSentTo($celebrant, BirthdayNotification::class);
        Notification::assertSentTo($colleagueSameCompany, BirthdayNotification::class);
        Notification::assertNotSentTo($colleagueOtherCompany, BirthdayNotification::class);
    }

    public function test_work_anniversary_reminder_excludes_same_year_joiners(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $celebrant = $this->makeUser('ANNIV1', $company->id, ['date_of_joining' => now()->subYears(3)->toDateString()]);
        $newHire = $this->makeUser('NEWHIRE1', $company->id, ['date_of_joining' => now()->toDateString()]);

        $this->artisan('hr:send-daily-reminders')->assertExitCode(0);

        Notification::assertSentTo($celebrant, WorkAnniversaryNotification::class);

        // newHire joined today (this year), so they must not be treated as a celebrant
        // themselves — they should receive exactly one email, as ANNIV1's colleague, not
        // a second copy for their own same-day-this-year "anniversary".
        Notification::assertSentToTimes($newHire, WorkAnniversaryNotification::class, 1);
    }

    public function test_upcoming_holiday_reminder_is_scoped_to_the_holidays_company(): void
    {
        $companyA = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $companyB = Company::firstOrCreate(['code' => 'BR'], ['name' => 'Branch Office', 'is_active' => true]);

        $employeeA = $this->makeUser('HOLA1', $companyA->id);
        $employeeB = $this->makeUser('HOLB1', $companyB->id);

        Holiday::create(['name' => 'Founders Day', 'date' => now()->addDay()->toDateString(), 'company_id' => $companyA->id]);

        $this->artisan('hr:send-daily-reminders')->assertExitCode(0);

        Notification::assertSentTo($employeeA, UpcomingHolidayNotification::class);
        Notification::assertNotSentTo($employeeB, UpcomingHolidayNotification::class);
    }

    public function test_org_wide_holiday_notifies_every_company(): void
    {
        $companyA = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $companyB = Company::firstOrCreate(['code' => 'BR'], ['name' => 'Branch Office', 'is_active' => true]);

        $employeeA = $this->makeUser('HOLA2', $companyA->id);
        $employeeB = $this->makeUser('HOLB2', $companyB->id);

        Holiday::create(['name' => 'National Holiday', 'date' => now()->addDay()->toDateString(), 'company_id' => null]);

        $this->artisan('hr:send-daily-reminders')->assertExitCode(0);

        Notification::assertSentTo($employeeA, UpcomingHolidayNotification::class);
        Notification::assertSentTo($employeeB, UpcomingHolidayNotification::class);
    }
}
