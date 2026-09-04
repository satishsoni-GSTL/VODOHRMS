<?php

namespace App\Filament\Pages\Auth;

use App\Models\LoginAudit;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * Transient per-minute throttle only (Filament's built-in brute-force guard, clears
     * after 60s). There is deliberately no persistent account lockout — a wrong password
     * never locks the account.
     */
    private const MAX_ATTEMPTS = 5;

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Employee Code or Email')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(self::MAX_ATTEMPTS);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $identifier = trim((string) $data['login']);

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('employee_code', $identifier)
            ->first();

        if ($user && ! $user->is_active) {
            $this->logAttempt($user, $identifier, 'inactive');
            $this->throwFailureValidationException('This account is inactive. Contact your HR administrator.');
        }

        $credentials = [
            'email' => $user?->email ?? '__no_such_user__',
            'password' => $data['password'],
        ];

        if (! Filament::auth()->attempt($credentials, $data['remember'] ?? false)) {
            $this->logAttempt($user, $identifier, 'invalid_credentials');
            $this->throwFailureValidationException('These credentials do not match our records.');
        }

        $authenticatedUser = Filament::auth()->user();

        if (
            ($authenticatedUser instanceof FilamentUser) &&
            (! $authenticatedUser->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();
            $this->logAttempt($user, $identifier, 'panel_access_denied');
            $this->throwFailureValidationException('You do not have access to this panel.');
        }

        $authenticatedUser->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        $this->logAttempt($authenticatedUser, $identifier, LoginAudit::STATUS_SUCCESS);

        session()->regenerate();

        return app(LoginResponse::class);
    }

    private function logAttempt(?User $user, string $identifier, string $reason): void
    {
        LoginAudit::create([
            'user_id' => $user?->id,
            'identifier' => $identifier,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status' => $reason === LoginAudit::STATUS_SUCCESS ? LoginAudit::STATUS_SUCCESS : LoginAudit::STATUS_FAILED,
            'reason' => $reason,
        ]);
    }

    protected function throwFailureValidationException(?string $message = null): never
    {
        throw ValidationException::withMessages([
            'data.login' => $message ?? __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $user = User::query()
            ->where('email', $data['login'])
            ->orWhere('employee_code', $data['login'])
            ->first();

        return [
            'email' => $user?->email ?? '__no_such_user__',
            'password' => $data['password'],
        ];
    }
}
