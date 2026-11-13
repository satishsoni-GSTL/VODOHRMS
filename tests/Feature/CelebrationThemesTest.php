<?php

namespace Tests\Feature;

use App\Filament\Resources\FestiveThemeResource\Pages\EditFestiveTheme;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FestiveTheme;
use App\Models\User;
use App\Support\FestiveThemes;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CelebrationThemesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-12-10 10:00:00');
        // Start from everything off (the migration seeds Diwali on for Nov 2026).
        FestiveTheme::query()->update(['is_enabled' => false]);
        FestiveThemes::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function theme(string $key): FestiveTheme
    {
        return FestiveTheme::where('key', $key)->firstOrFail();
    }

    private function user(string $role, array $employee = []): User
    {
        $company = Company::firstOrCreate(['code' => 'GS'], ['name' => 'GlobalSpace', 'is_active' => true]);
        $emp = Employee::create($employee + [
            'employee_code' => 'E'.random_int(1000, 9999), 'first_name' => 'Asha', 'last_name' => 'Rao',
            'official_email' => uniqid().'@example.test', 'company_id' => $company->id,
            'date_of_joining' => '2024-01-01', 'status' => Employee::STATUS_ACTIVE,
        ]);
        $user = User::create([
            'employee_id' => $emp->id, 'employee_code' => $emp->employee_code, 'name' => 'Asha Rao',
            'email' => $emp->official_email, 'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_all_themes_are_seeded(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(FestiveThemes::DEFINITIONS), FestiveTheme::pluck('key')->all());
    }

    public function test_nothing_shows_unless_switched_on_and_in_window(): void
    {
        $this->assertNull(FestiveThemes::current());
        $this->get('/admin/login')->assertOk()->assertDontSee('festive.css')->assertDontSee('data-fx-stage', false);

        // Christmas is seeded for 20–26 Dec; switch it on.
        $this->theme('christmas')->update(['is_enabled' => true]);
        $this->assertNull(FestiveThemes::current()); // 10 Dec: not yet

        Carbon::setTestNow('2026-12-22 09:00:00');
        $this->assertSame('christmas', FestiveThemes::currentKey());
        $this->get('/admin/login')->assertOk()
            ->assertSee('css/festive/festive.css')
            ->assertSee('data-fx-effect="snow"', false)
            ->assertSee('Merry Christmas');

        Carbon::setTestNow('2026-12-27 09:00:00');
        $this->assertNull(FestiveThemes::current());
    }

    public function test_manual_theme_without_dates_and_priority(): void
    {
        $this->theme('annual_day')->update(['is_enabled' => true, 'starts_on' => null, 'ends_on' => null, 'venue' => 'Taj, Pune',
            'days' => [['name' => 'Annual Day', 'date' => '2026-12-18']]]);
        $this->assertSame('annual_day', FestiveThemes::currentKey());

        // A dated window beats an open-ended theme.
        Carbon::setTestNow('2026-12-22 09:00:00');
        $this->theme('christmas')->update(['is_enabled' => true]);
        $this->assertSame('christmas', FestiveThemes::currentKey());
        $this->assertSame('Overlaps — another theme is showing', $this->theme('annual_day')->statusLabel());
        $this->assertSame('Live now', $this->theme('christmas')->statusLabel());
    }

    public function test_master_switch(): void
    {
        $this->theme('annual_day')->update(['is_enabled' => true]);
        config(['festive.enabled' => false]);
        FestiveThemes::flush();

        $this->assertNull(FestiveThemes::current());
    }

    public function test_dashboard_banner_with_countdown_and_colours(): void
    {
        $this->theme('annual_day')->update(['is_enabled' => true, 'venue' => 'Taj, Pune',
            'days' => [['name' => 'Annual Day', 'date' => '2026-12-18']]]);

        $this->actingAs($this->user('Employee'))
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-fx-welcome="annual_day"', false)
            ->assertSee('GlobalSpace Annual Day 2026')
            ->assertSee('at Taj, Pune')
            ->assertSee('8 days to Annual Day')
            ->assertSee('Asha');

        $this->assertNotNull(FestiveThemes::colors()['primary']);
    }

    public function test_birthday_is_personal(): void
    {
        $this->theme('birthday')->update(['is_enabled' => true]);
        $birthdayPerson = $this->user('Employee', ['dob' => '1995-12-10']);
        $colleague = $this->user('Employee', ['dob' => '1995-06-01']);

        $this->actingAs($birthdayPerson)->get('/admin')->assertSee('Happy Birthday, Asha!', false)->assertSee('data-fx-effect="balloons"', false);

        FestiveThemes::flush();
        $this->flushSession();
        $this->actingAs($colleague)->get('/admin')->assertDontSee('Happy Birthday', false);

        // Login page never shows the birthday theme (nobody is signed in yet).
        FestiveThemes::flush();
        auth()->logout();
        $this->flushSession();
        $this->get('/admin/login')->assertDontSee('data-fx-stage', false);
    }

    public function test_preview_only_affects_this_session(): void
    {
        $user = $this->user('HR Admin');

        $this->actingAs($user)->get('/admin?festive_preview=holi')->assertSee('Preview — only you can see this', false)->assertSee('होली मुबारक');
        $this->get('/admin')->assertSee('data-fx-welcome="holi"', false); // still in this session
        $this->get('/admin?festive_preview=off')->assertDontSee('data-fx-welcome', false);

        // The sign-in page can be previewed too (signed out — a signed-in user is redirected).
        auth()->logout();
        $this->get('/admin/login?festive_preview=independence_day')->assertOk()->assertSee('data-fx-effect="tricolour"', false)->assertSee('जय हिन्द');
        $this->get('/admin/login?festive_preview=nonsense')->assertOk();
    }

    public function test_every_theme_renders(): void
    {
        $this->actingAs($this->user('HR Admin'));

        foreach (array_keys(FestiveThemes::DEFINITIONS) as $key) {
            FestiveThemes::flush();
            $this->get("/admin?festive_preview={$key}")->assertOk();
        }
    }

    public function test_diwali_still_uses_its_own_artwork(): void
    {
        $this->theme('diwali')->update(['is_enabled' => true, 'starts_on' => '2027-10-25', 'ends_on' => '2027-11-02',
            'days' => [['name' => 'Diwali', 'date' => '2027-10-29']], 'main_day' => 'Diwali']);

        Carbon::setTestNow('2027-10-27 10:00:00');
        $this->get('/admin/login')->assertOk()->assertSee('css/festive/diwali.css')->assertSee('data-dw-stage', false)->assertSee('2 days to Diwali');
    }

    public function test_admin_screen(): void
    {
        $hr = $this->user('HR Admin');
        $this->actingAs($hr)->get('/admin/festive-themes')->assertOk()->assertSee('Annual Day')->assertSee('Independence Day');

        Livewire::test(EditFestiveTheme::class, ['record' => $this->theme('new_year')->getRouteKey()])
            ->fillForm(['is_enabled' => true, 'title' => 'Cheers to {year}, {name}!'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($this->theme('new_year')->is_enabled);

        $this->flushSession();
        $this->actingAs($this->user('Employee'))->get('/admin/festive-themes')->assertForbidden();
    }
}
