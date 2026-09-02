<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginEmailSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployeeWithLogin(string $code, string $email): Employee
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => $email,
            'company_id' => $company->id,
            'date_of_joining' => now(),
            'status' => Employee::STATUS_ACTIVE,
        ]);

        User::create([
            'employee_id' => $employee->id,
            'employee_code' => $code,
            'name' => $code,
            'email' => $email,
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);

        return $employee->fresh();
    }

    public function test_changing_official_email_repoints_the_login(): void
    {
        $employee = $this->makeEmployeeWithLogin('EMP900', 'emp900@globalspace.in');

        $employee->update(['official_email' => 'emp900@makebot.in']);

        $this->assertSame('emp900@makebot.in', $employee->user->fresh()->email);
    }

    public function test_falls_back_to_personal_email_when_official_is_cleared(): void
    {
        $employee = $this->makeEmployeeWithLogin('EMP901', 'emp901@globalspace.in');
        $employee->update(['personal_email' => 'emp901@gmail.com']);

        $employee->update(['official_email' => null]);

        $this->assertSame('emp901@gmail.com', $employee->user->fresh()->email);
    }

    public function test_sync_is_skipped_when_target_belongs_to_another_login(): void
    {
        // Another login already holds this address (official_email is unique per employee,
        // but a personal_email can collide with someone else's login).
        $this->makeEmployeeWithLogin('EMP902', 'taken@makebot.in');
        $employee = $this->makeEmployeeWithLogin('EMP903', 'emp903@globalspace.in');
        $employee->update(['personal_email' => 'taken@makebot.in']);

        $employee->update(['official_email' => null]);

        // Login left untouched rather than colliding.
        $this->assertSame('emp903@globalspace.in', $employee->user->fresh()->email);
    }

    public function test_command_reconciles_existing_mismatches(): void
    {
        $employee = $this->makeEmployeeWithLogin('EMP904', 'emp904@globalspace.in');
        // Force a drift without going through the model hook.
        User::where('employee_id', $employee->id)->update(['email' => 'stale@globalspace.in']);

        $this->artisan('users:sync-login-emails')
            ->expectsOutputToContain('Would update 1 login(s):')
            ->assertSuccessful();

        $this->assertSame('stale@globalspace.in', $employee->user->fresh()->email);

        $this->artisan('users:sync-login-emails --commit')->assertSuccessful();

        $this->assertSame('emp904@globalspace.in', $employee->user->fresh()->email);
    }
}
