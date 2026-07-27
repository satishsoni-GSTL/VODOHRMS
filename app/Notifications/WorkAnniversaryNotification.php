<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Notifications\Messages\MailMessage;

class WorkAnniversaryNotification extends BaseNotification
{
    public function __construct(private readonly Employee $celebrant) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $years = (string) $this->celebrant->date_of_joining->diffInYears(now());
        $isCelebrant = ($notifiable->employee_id ?? null) === $this->celebrant->id;

        $replacements = [
            '{employee_name}' => $this->celebrant->full_name,
            '{years}' => $years,
        ];

        $rendered = $isCelebrant
            ? $this->renderTemplate('work_anniversary_self', $replacements, 'Happy {years}-Year Work Anniversary!', [
                'Congratulations, {employee_name}!',
                'Today marks {years} year(s) since you joined us. Thank you for your contributions!',
            ])
            : $this->renderTemplate('work_anniversary_others', $replacements, "{employee_name}'s Work Anniversary Today", [
                "Today is {employee_name}'s {years}-year work anniversary.",
                'Take a moment to congratulate them!',
            ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail;
    }
}
