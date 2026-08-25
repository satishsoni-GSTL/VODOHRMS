<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Self-service forgot-password email: a 6-digit code the user types into
 * App\Filament\Pages\Auth\ResetPasswordWithOtp, rather than a clicked link. Unlike
 * PasswordResetByAdminNotification, the account's password itself never appears here.
 */
class PasswordResetOtpNotification extends BaseNotification
{
    public function __construct(
        private readonly User $user,
        private readonly string $otp,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replacements = [
            '{name}' => $this->user->name,
            '{otp}' => $this->otp,
            '{minutes}' => '10',
        ];

        $rendered = $this->renderTemplate('password_reset_otp', $replacements, 'Your VODOHRMS password reset code', [
            'Hi {name}, use this code to reset your VODOHRMS password:',
            '{otp}',
            'This code expires in {minutes} minutes.',
            "If you didn't request this, you can ignore this email.",
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail;
    }
}
