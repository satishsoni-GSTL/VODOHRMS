<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetByAdminNotification extends BaseNotification
{
    public function __construct(
        private readonly User $user,
        private readonly string $plainPassword,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replacements = [
            '{name}' => $this->user->name,
            '{employee_code}' => $this->user->employee_code,
            '{email}' => $this->user->email,
            '{new_password}' => $this->plainPassword,
        ];

        $rendered = $this->renderTemplate('password_reset', $replacements, 'Your VODOHRMS password has been reset', [
            'Hi {name}, your password on VODOHRMS was reset by an administrator.',
            'Employee code: {employee_code}',
            'Email: {email}',
            'New password: {new_password}',
            'You may be required to change this password on your next login.',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action('Log In', url('/admin/login'));
    }
}
