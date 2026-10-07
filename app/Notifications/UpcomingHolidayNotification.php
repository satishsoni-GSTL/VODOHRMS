<?php

namespace App\Notifications;

use App\Models\Holiday;
use Illuminate\Notifications\Messages\MailMessage;

class UpcomingHolidayNotification extends BaseNotification
{
    public function __construct(private readonly Holiday $holiday) {}

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable);
    }

    public function toPush(object $notifiable): ?array
    {
        return [
            'title' => 'Upcoming holiday: '.trim($this->holiday->name),
            'body' => $this->holiday->date->format('l, d M Y'),
            'data' => ['screen' => 'holidays'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replacements = [
            '{holiday_name}' => $this->holiday->name,
            '{holiday_date}' => $this->holiday->date->toFormattedDateString(),
        ];

        $rendered = $this->renderTemplate('upcoming_holiday', $replacements, 'Upcoming Holiday: {holiday_name}', [
            'Tomorrow, {holiday_date}, is a holiday: {holiday_name}.',
            'Plan your work accordingly.',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail;
    }
}
