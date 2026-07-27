<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeAccountNotification extends BaseNotification
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
            '{temp_password}' => $this->plainPassword,
        ];

        $rendered = $this->renderTemplate('welcome_account', $replacements, 'Your VODOHRMS login has been created', [
            'Welcome, {name}! An account has been created for you on VODOHRMS.',
            'Employee code: {employee_code}',
            'Email: {email}',
            'Temporary password: {temp_password}',
            'You will be required to change this password on your first login.',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action('Log In', url('/admin/login'));
    }
}
