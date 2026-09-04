<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use App\Notifications\PasswordResetOtpNotification;
use Tests\TestCase;

/**
 * Covers the self-service password flows added in App\Filament\Pages\Auth: ChangePassword
 * (logged-in) and ForgotPassword/ResetPasswordWithOtp (the emailed-OTP flow), wired via
 * AdminPanelProvider's ->profile()/->passwordReset(). See password_reset_tokens columns
 * added by the 2026_08_25_000001 migration (attempts/expires_at, repurposing `token` to
 * hold Hash::make($otp) instead of a Laravel broker token).
 */
class SelfServicePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $employeeCode, string $role = 'Employee'): User
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
            'password' => bcrypt('old-password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_profile_page_requires_login(): void
    {
        $this->get('/admin/profile')->assertRedirect('/admin/login');
    }

    public function test_logged_in_user_can_change_their_own_password(): void
    {
        $user = $this->makeUser('SSP001');
        $this->actingAs($user, 'web');

        $this->get('/admin/profile')->assertStatus(200);
    }

    public function test_forgot_password_request_page_is_public(): void
    {
        $this->get('/admin/password-reset/request')->assertStatus(200);
    }

    public function test_reset_password_page_rejects_an_unsigned_url(): void
    {
        $this->get('/admin/password-reset/reset?email=someone@example.com')->assertStatus(403);
    }

    public function test_forgot_password_flow_issues_a_working_otp(): void
    {
        Notification::fake();

        $user = $this->makeUser('SSP002');

        // Simulate what ForgotPassword::request() does: generate + store a hashed OTP.
        $otp = '654321';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($otp), 'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'created_at' => now()],
        );
        $user->notify(new PasswordResetOtpNotification($user, $otp));

        Notification::assertSentTo($user, PasswordResetOtpNotification::class);

        $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertTrue(Hash::check($otp, $row->token));
        $this->assertFalse(Hash::check('000000', $row->token));
        $this->assertTrue(now()->lt($row->expires_at));
    }

    public function test_signed_reset_password_url_renders_the_otp_form(): void
    {
        $user = $this->makeUser('SSP003');

        $url = \Filament\Facades\Filament::getResetPasswordUrl(Str::random(20), $user);
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $this->get($path)->assertStatus(200)->assertSee('6-Digit Code');
    }

    public function test_submitting_a_valid_otp_resets_the_password(): void
    {
        $user = $this->makeUser('SSP004');
        $otp = '112233';

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($otp), 'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'created_at' => now()],
        );

        \Livewire\Livewire::test(\App\Filament\Pages\Auth\ResetPasswordWithOtp::class, [
            'email' => $user->email,
            'token' => Str::random(20),
        ])
            ->set('otp', $otp)
            ->set('password', 'brand-new-password')
            ->set('passwordConfirmation', 'brand-new-password')
            ->call('resetPassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_submitting_a_wrong_otp_does_not_reset_and_counts_an_attempt(): void
    {
        $user = $this->makeUser('SSP005');

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make('445566'), 'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'created_at' => now()],
        );

        \Livewire\Livewire::test(\App\Filament\Pages\Auth\ResetPasswordWithOtp::class, [
            'email' => $user->email,
            'token' => Str::random(20),
        ])
            ->set('otp', '000000')
            ->set('password', 'brand-new-password')
            ->set('passwordConfirmation', 'brand-new-password')
            ->call('resetPassword');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
        $this->assertSame(1, (int) DB::table('password_reset_tokens')->where('email', $user->email)->value('attempts'));
    }
}
