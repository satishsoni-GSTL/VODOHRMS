<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\PasswordResetResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\ResetPassword as BaseResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Step 2 of the self-service forgot-password flow: verify the 6-digit code emailed by
 * App\Filament\Pages\Auth\ForgotPassword and set a new password. Reached via Filament's
 * normal signed password-reset route, but ignores the `$token` query param entirely —
 * validation is against the hashed OTP in `password_reset_tokens`, not Password::broker().
 */
class ResetPasswordWithOtp extends BaseResetPassword
{
    private const MAX_ATTEMPTS = 5;

    /**
     * The base ResetPassword page binds its form fields to top-level Livewire properties
     * (not a `data` state path), so the extra OTP field needs its own backing property —
     * without it Livewire throws "No property found for validation: [otp]" on submit.
     */
    public ?string $otp = '';

    public function mount(?string $email = null, ?string $token = null): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill([
            'email' => $email ?? request()->query('email'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            $this->getEmailFormComponent(),
            $this->getOtpFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }

    protected function getOtpFormComponent(): Component
    {
        return TextInput::make('otp')
            ->label('6-Digit Code')
            ->required()
            ->rule('digits:6')
            ->autofocus();
    }

    public function resetPassword(): ?PasswordResetResponse
    {
        try {
            $this->rateLimit(self::MAX_ATTEMPTS);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $email = $this->email;

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        $isValid = $record
            && $record->expires_at !== null
            && now()->lt($record->expires_at)
            && $record->attempts < self::MAX_ATTEMPTS
            && Hash::check($data['otp'], $record->token);

        if (! $isValid) {
            if ($record) {
                DB::table('password_reset_tokens')->where('email', $email)->increment('attempts');
            }

            Notification::make()
                ->title('Invalid or expired code')
                ->body('Request a new code and try again.')
                ->danger()
                ->send();

            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || (($user instanceof FilamentUser) && ! $user->canAccessPanel(Filament::getCurrentPanel()))) {
            Notification::make()->title('This account cannot be reset here.')->danger()->send();

            return null;
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        Notification::make()->title('Password reset successfully')->success()->send();

        return app(PasswordResetResponse::class);
    }

    public function requestNewCodeAction(): Action
    {
        return Action::make('requestNewCode')
            ->link()
            ->label('Request a new code')
            ->url(Filament::getRequestPasswordResetUrl());
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getResetPasswordFormAction(),
            $this->requestNewCodeAction(),
        ];
    }
}
