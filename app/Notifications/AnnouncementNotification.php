<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class AnnouncementNotification extends BaseNotification
{
    public function __construct(
        private readonly string $announcementSubject,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->announcementSubject);

        foreach (explode("\n", $this->message) as $line) {
            if (trim($line) !== '') {
                $mail->line($line);
            }
        }

        return $mail;
    }
}
