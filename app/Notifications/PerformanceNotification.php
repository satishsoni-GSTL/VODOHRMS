<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Simple one-line notice with an optional link: PMS steps (goals to set / approve, review
 * due...), pre-joining submissions and the like.
 */
class PerformanceNotification extends BaseNotification
{
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $path = '/admin/appraisals',
        private readonly string $actionLabel = 'Open Performance',
    ) {}

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable);
    }

    public function toPush(object $notifiable): ?array
    {
        return ['title' => $this->title, 'body' => $this->body, 'data' => ['screen' => 'home']];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->line($this->body);

        return $this->path ? $mail->action($this->actionLabel, url($this->path)) : $mail;
    }
}
