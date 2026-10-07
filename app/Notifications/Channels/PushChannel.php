<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\FirebasePushService;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel that delivers a notification's toPush() payload to the
 * user's phones (employee mobile app) via Firebase. Opt in per notification with
 * BaseNotification::withPush().
 */
class PushChannel
{
    public function __construct(private readonly FirebasePushService $push) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toPush')) {
            return;
        }

        /** @var array{title: string, body: string, data?: array<string, scalar|null>}|null $payload */
        $payload = $notification->toPush($notifiable);

        if ($payload) {
            $this->push->sendToUser($notifiable, $payload['title'], $payload['body'], $payload['data'] ?? []);
        }
    }
}
