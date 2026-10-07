<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\CelebrationService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CelebrationsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-12-28 09:00'));
        $this->company = Company::create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(string $code, array $attrs = []): Employee
    {
        return Employee::create($attrs + [
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $this->company->id,
            'date_of_joining' => '2020-06-15',
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    public function test_company_wide_birthdays_and_anniversaries_for_the_coming_week(): void
    {
        $manager = $this->employee('MGR1');
        $this->employee('TEAM1', ['reporting_manager_id' => $manager->id, 'dob' => '1990-12-28']); // my team, today
        $this->employee('OTHER1', ['dob' => '1988-12-30']);                                           // another team, in 2 days
        $this->employee('OTHER2', ['date_of_joining' => '2023-01-02']);                               // 4-year anniversary, next year (wrap)
        $this->employee('LATER', ['dob' => '1990-01-10']);                                            // beyond 7 days
        $this->employee('LEFT', ['dob' => '1990-12-29', 'status' => Employee::STATUS_EXITED]);       // ex-employee
        $this->employee('NEWBIE', ['date_of_joining' => '2026-12-29']);                               // joining day, not an anniversary

        $otherCompany = Company::create(['code' => 'BR', 'name' => 'Branch Co', 'is_active' => true]);
        $this->employee('OTHERCO', ['dob' => '1990-12-28', 'company_id' => $otherCompany->id]);

        $items = app(CelebrationService::class)->upcoming($manager, 7);

        $this->assertSame(
            ['TEAM1:birthday', 'OTHER1:birthday', 'OTHER2:anniversary'],
            $items->map(fn ($c) => $c['employee']->employee_code.':'.$c['type'])->all(),
        );
        $this->assertTrue($items[0]['is_today']);
        $this->assertTrue($items[0]['is_my_team']);
        $this->assertFalse($items[1]['is_my_team']);
        $this->assertSame(2, $items[1]['days_away']);
        $this->assertSame('2027-01-02', $items[2]['date']);
        $this->assertSame(4, $items[2]['years']);
    }

    public function test_feb_29_birthday_is_celebrated_on_feb_28_in_non_leap_years(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-25'));
        $viewer = $this->employee('VIEW');
        $this->employee('LEAP', ['dob' => '1996-02-29']);

        $items = app(CelebrationService::class)->upcoming($viewer, 7);

        $this->assertSame('2027-02-28', $items->firstWhere('employee.employee_code', 'LEAP')['date']);
    }

    public function test_dashboard_widget_and_mobile_api_show_celebrations(): void
    {
        $me = $this->employee('ME1');
        $this->employee('BDAY', ['first_name' => 'Asha', 'dob' => '1992-12-28']);

        $user = User::create([
            'employee_id' => $me->id, 'employee_code' => 'ME1', 'name' => 'ME1', 'email' => 'me1@vodohrms.local',
            'password' => bcrypt('secret123'), 'is_active' => true,
        ]);
        $user->assignRole('Employee');

        // Dashboard widgets load lazily, so render the widget itself.
        $this->actingAs($user, 'web')->get('/admin')->assertOk();
        \Livewire\Livewire::test(\App\Filament\Widgets\Celebrations::class)
            ->assertSee('Birthdays & Work Anniversaries')
            ->assertSee('Asha')
            ->assertSee('Birthday today');

        $token = $this->postJson('/api/mobile/login', ['login' => 'ME1', 'password' => 'secret123'])->json('token');
        $this->withToken($token)->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('celebrations.0.name', 'Asha')
            ->assertJsonPath('celebrations.0.type', 'birthday')
            ->assertJsonPath('celebrations.0.is_today', true)
            ->assertJsonMissingPath('celebrations.0.dob');
    }
}
