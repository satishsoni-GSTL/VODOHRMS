<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Services\FirebasePushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Throwable;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Render a DB-editable template if one exists for $key, otherwise fall back to the
     * given defaults. Placeholders in the template/default text use {curly_braces} and are
     * substituted from $replacements (e.g. ['{employee_name}' => 'Jane Doe']).
     *
     * @param  array<string, string>  $replacements
     * @param  string[]  $defaultBodyLines
     * @return array{subject: string, lines: string[]}
     */
    protected function renderTemplate(string $key, array $replacements, string $defaultSubject, array $defaultBodyLines): array
    {
        $template = NotificationTemplate::where('key', $key)->first();

        $subject = $template?->subject ?: $defaultSubject;
        $lines = $template?->body ? explode("\n", $template->body) : $defaultBodyLines;

        $renderedLines = array_map(fn (string $line) => trim(strtr($line, $replacements)), $lines);

        return [
            'subject' => strtr($subject, $replacements),
            'lines' => array_values(array_filter($renderedLines, fn (string $line) => $line !== '')),
        ];
    }

    /**
     * Adds the mobile-app push channel for real users (not plain e-mail routes) once Firebase
     * is configured. The notification must implement toPush().
     *
     * @param  string[]  $channels
     * @return array<int, string>
     */
    protected function withPush(object $notifiable, array $channels = ['mail']): array
    {
        if ($notifiable instanceof User && app(FirebasePushService::class)->isConfigured()) {
            $channels[] = PushChannel::class;
        }

        return $channels;
    }

    /**
     * Push payload for the mobile app. Default: the e-mail's subject as the title and its
     * first line as the body (so DB-edited templates apply to push too). Override to add
     * `data.screen` — the app tab to open when the notification is tapped.
     *
     * @return array{title: string, body: string, data?: array<string, scalar|null>}|null
     */
    public function toPush(object $notifiable): ?array
    {
        if (! method_exists($this, 'toMail')) {
            return null;
        }

        $mail = $this->toMail($notifiable);

        return [
            'title' => (string) ($mail->subject ?? config('app.name')),
            'body' => (string) ($mail->introLines[0] ?? ''),
            'data' => ['screen' => 'home'],
        ];
    }

    public function failed(Throwable $exception): void
    {
        NotificationLog::where('notification_id', $this->id)->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ]);
    }
}
