<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\BiometricDevice;
use App\Models\Company;
use App\Models\DevicePunchLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The biometric sync API trusts a device token. A leaked token must not let anyone post
 * punches from outside the office network, or forge future / ancient punches.
 */
class BiometricApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    private BiometricDevice $device;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00');

        $company = Company::create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
        Employee::create([
            'employee_code' => 'BIO1', 'first_name' => 'Bio', 'official_email' => 'bio@x.test', 'company_id' => $company->id,
            'date_of_joining' => '2025-01-01', 'status' => Employee::STATUS_ACTIVE, 'biometric_enroll_id' => '42',
        ]);

        $this->device = BiometricDevice::create(['name' => 'Office', 'code' => 'OFF', 'is_active' => true, 'allowed_ips' => '106.219.135.64', 'api_token_hash' => 'pending']);
        $this->token = $this->device->issueToken();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sendPunch(string $ip, string $time)
    {
        return $this->withToken($this->token)
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/biometric/punches', ['punches' => [['device_user_id' => '42', 'punch_time' => $time]]]);
    }

    public function test_punch_from_the_office_ip_is_accepted_and_its_ip_recorded(): void
    {
        $this->sendPunch('106.219.135.64', '2026-10-07 09:31:00')->assertOk()->assertJsonPath('summary.matched', 1);

        $this->assertSame('106.219.135.64', DevicePunchLog::sole()->source_ip);
        $this->assertSame('09:31:00', Attendance::sole()->first_in);
    }

    public function test_leaked_token_used_from_another_network_is_refused_and_logged(): void
    {
        $this->sendPunch('49.36.10.20', '2026-10-07 09:31:00')->assertForbidden();

        $this->assertSame(0, DevicePunchLog::count());
        $this->assertSame(0, Attendance::count());
        $this->assertStringContainsString('49.36.10.20', AuditLog::where('action', 'tampering_blocked')->sole()->reason);
    }

    public function test_ip_ranges_and_lists_are_supported_and_empty_means_any(): void
    {
        $this->device->update(['allowed_ips' => '10.0.0.5, 106.219.135.0/24']);
        $this->sendPunch('106.219.135.200', '2026-10-07 09:31:00')->assertOk();

        $this->device->update(['allowed_ips' => null]);
        $this->sendPunch('1.2.3.4', '2026-10-07 09:45:00')->assertOk();
    }

    public function test_future_and_very_old_punch_times_are_rejected(): void
    {
        $this->sendPunch('106.219.135.64', '2026-10-08 09:00:00')->assertStatus(422);  // tomorrow
        $this->sendPunch('106.219.135.64', '2026-07-01 09:00:00')->assertStatus(422);  // > 45 days ago
        $this->sendPunch('106.219.135.64', '2026-10-01 09:00:00')->assertOk();         // recent back-fill is fine

        $this->assertSame(1, DevicePunchLog::count());
    }
}
