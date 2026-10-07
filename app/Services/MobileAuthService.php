<?php

namespace App\Services;

use App\Models\LoginAudit;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One-time login for the employee mobile app. The same rules as the web login apply
 * (employee code or email, active account, login audit); on success a long-lived device
 * token is issued. Only its sha256 is stored, so a database leak can't be replayed.
 */
class MobileAuthService
{
    /**
     * @return array{0: string, 1: MobileDevice} [plain token, device]
     */
    public function login(string $identifier, string $password, ?string $deviceName, ?string $platform, ?string $appVersion, ?string $ip, ?string $userAgent): array
    {
        $identifier = trim($identifier);

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('employee_code', $identifier)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->audit($user, $identifier, 'invalid_credentials', $ip, $userAgent);

            throw ValidationException::withMessages(['login' => 'These credentials do not match our records.']);
        }

        if (! $user->is_active) {
            $this->audit($user, $identifier, 'inactive', $ip, $userAgent);

            throw ValidationException::withMessages(['login' => 'This account is inactive. Contact your HR administrator.']);
        }

        if (! $user->employee_id) {
            $this->audit($user, $identifier, 'no_employee_record', $ip, $userAgent);

            throw ValidationException::withMessages(['login' => 'The mobile app is for employees. This login is not linked to an employee record.']);
        }

        $plain = Str::random(64);

        $device = MobileDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'device_name' => $deviceName ? Str::limit($deviceName, 250, '') : null,
            'platform' => $platform ? Str::limit($platform, 20, '') : null,
            'app_version' => $appVersion ? Str::limit($appVersion, 20, '') : null,
            'last_used_at' => now(),
            'last_ip' => $ip,
        ]);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->save();
        $this->audit($user, $identifier, LoginAudit::STATUS_SUCCESS, $ip, $userAgent);

        return [$plain, $device];
    }

    /**
     * The active device (and still-active user) for a token, or null. Touches last-used.
     */
    public function deviceForToken(?string $plain, ?string $ip = null): ?MobileDevice
    {
        if (blank($plain)) {
            return null;
        }

        $device = MobileDevice::query()
            ->active()
            ->where('token_hash', hash('sha256', $plain))
            ->with('user')
            ->first();

        if (! $device || ! $device->user?->is_active) {
            return null;
        }

        // Every API call passes through here; only record "last used" every few minutes.
        if (! $device->last_used_at || $device->last_used_at->lt(now()->subMinutes(5)) || $device->last_ip !== $ip) {
            $device->forceFill(['last_used_at' => now(), 'last_ip' => $ip])->save();
        }

        return $device;
    }

    public function revoke(MobileDevice $device): void
    {
        $device->forceFill(['revoked_at' => now(), 'fcm_token' => null])->save();
    }

    private function audit(?User $user, string $identifier, string $reason, ?string $ip, ?string $userAgent): void
    {
        LoginAudit::create([
            'user_id' => $user?->id,
            'identifier' => $identifier,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit('[mobile] '.$userAgent, 500, '') : '[mobile]',
            'status' => $reason === LoginAudit::STATUS_SUCCESS ? LoginAudit::STATUS_SUCCESS : LoginAudit::STATUS_FAILED,
            'reason' => $reason,
        ]);
    }
}
