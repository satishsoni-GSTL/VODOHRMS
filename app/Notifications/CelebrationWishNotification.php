<?php

namespace App\Notifications;

use App\Notifications\Channels\PushChannel;
use App\Services\FirebasePushService;

/**
 * The 9 AM personal birthday / work-anniversary wish, pushed to the celebrating employee's
 * phone. Push only — colleagues' reminder e-mails are sent separately at 8 AM.
 */
class CelebrationWishNotification extends BaseNotification
{
    public function __construct(private readonly string $title, private readonly string $message) {}

    public function via(object $notifiable): array
    {
        return app(FirebasePushService::class)->isConfigured() ? [PushChannel::class] : [];
    }

    public function toPush(object $notifiable): ?array
    {
        return [
            'title' => $this->title,
            'body' => $this->message,
            'data' => ['screen' => 'home', 'kind' => 'celebration'],
        ];
    }
}
