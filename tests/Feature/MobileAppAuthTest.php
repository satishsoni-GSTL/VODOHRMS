<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\MobileDevice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAppAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeEmployeeUser(string $code = 'MOB001', bool $active = true): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => 'Mobile',
            'last_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now(),
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $code,
            'name' => "Mobile {$code}",
            'email' => $employee->official_email,
            'password' => bcrypt('secret123'),
            'is_active' => $active,
        ]);
        $user->assignRole('Employee');

        return $user;
    }

    private function login(string $login = 'MOB001', string $password = 'secret123')
    {
        return $this->postJson('/api/mobile/login', [
            'login' => $login, 'password' => $password, 'device_name' => 'Pixel 8', 'platform' => 'android', 'app_version' => '1.0.0',
        ]);
    }

    public function test_employee_logs_in_once_with_employee_code_and_gets_a_device_token(): void
    {
        $this->makeEmployeeUser();

        $response = $this->login()->assertOk()->assertJsonPath('user.employee_code', 'MOB001');
        $token = $response->json('token');

        $this->assertSame(64, strlen($token));
        $device = MobileDevice::firstOrFail();
        $this->assertSame(hash('sha256', $token), $device->token_hash); // only the hash is stored
        $this->assertSame('Pixel 8', $device->device_name);

        $this->withToken($token)->getJson('/api/mobile/me')->assertOk()->assertJsonPath('user.name', 'Mobile MOB001');
    }

    public function test_wrong_password_inactive_and_non_employee_logins_are_refused(): void
    {
        $this->makeEmployeeUser();
        $this->login('MOB001', 'wrong')->assertStatus(422);

        $this->makeEmployeeUser('MOB002', active: false);
        $this->login('MOB002')->assertStatus(422)->assertJsonValidationErrors('login');

        User::create(['name' => 'No Employee', 'email' => 'noemp@vodohrms.local', 'password' => bcrypt('secret123'), 'is_active' => true]);
        $this->login('noemp@vodohrms.local')->assertStatus(422);

        $this->assertSame(0, MobileDevice::count());
    }

    public function test_public_privacy_policy_page_for_the_play_store(): void
    {
        config(['services.privacy.contact' => 'hr@globalspace.test']);

        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Information we process')
            ->assertSee('hr@globalspace.test');
    }

    public function test_api_requires_a_valid_device_token(): void
    {
        $this->getJson('/api/mobile/dashboard')->assertUnauthorized();
        $this->withToken('not-a-token')->getJson('/api/mobile/dashboard')->assertUnauthorized();
    }

    public function test_logout_revokes_the_device(): void
    {
        $this->makeEmployeeUser();
        $token = $this->login()->json('token');

        $this->withToken($token)->postJson('/api/mobile/logout')->assertOk();
        $this->assertNotNull(MobileDevice::first()->revoked_at);

        $this->withToken($token)->getJson('/api/mobile/me')->assertUnauthorized();
    }

    public function test_deactivating_the_employee_login_blocks_the_app(): void
    {
        $user = $this->makeEmployeeUser();
        $token = $this->login()->json('token');

        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/mobile/me')->assertUnauthorized();
    }
}
