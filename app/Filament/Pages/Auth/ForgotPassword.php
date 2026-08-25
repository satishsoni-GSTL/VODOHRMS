<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Step 1 of the self-service forgot-password flow: identify the account, email it a
 * 6-digit OTP (App\Notifications\PasswordResetOtpNotification), then hand off to
 * App\Filament\Pages\Auth\ResetPasswordWithOtp for the user to type the code in. Bypasses
 * Laravel's link-based Password::broker() entirely — see the plan notes on why the OTP
 * can still ride Filament's signed password-reset route without a real broker token.
 */
class ForgotPassword extends BaseRequestPasswordReset
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Employee Code or Email')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $identifier = trim((string) $this->form->getState()['email']);

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('employee_code', $identifier)
            ->first();

        if ($user && $user->is_active) {
            $otp = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($otp), 'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'created_at' => now()],
            );

            $user->notify(new PasswordResetOtpNotification($user, $otp));
        }

        // Same message whether or not an account was found — only the (invisible) redirect
        // differs, so the response text alone never confirms/denies account existence.
        Notification::make()
            ->title('Check your email')
            ->body("If that account exists, we've emailed a 6-digit code to reset the password.")
            ->success()
            ->send();

        if ($user && $user->is_active) {
            $this->redirect(Filament::getResetPasswordUrl(Str::random(20), $user));

            return;
        }

        $this->form->fill();
    }
}
