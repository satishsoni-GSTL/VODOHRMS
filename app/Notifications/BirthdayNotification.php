<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Notifications\Messages\MailMessage;

class BirthdayNotification extends BaseNotification
{
    public function __construct(private readonly Employee $celebrant) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isCelebrant = ($notifiable->employee_id ?? null) === $this->celebrant->id;
        $replacements = ['{employee_name}' => $this->celebrant->full_name];

        $rendered = $isCelebrant
            ? $this->renderTemplate('birthday_self', $replacements, 'Happy Birthday!', [
                'Happy Birthday, {employee_name}!',
                'Wishing you a wonderful year ahead. Have a great day!',
            ])
            : $this->renderTemplate('birthday_others', $replacements, "It's {employee_name}'s Birthday Today", [
                "Today is {employee_name}'s birthday.",
                'Take a moment to wish them well!',
            ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail;
    }
}
