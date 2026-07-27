<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
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

    public function failed(Throwable $exception): void
    {
        NotificationLog::where('notification_id', $this->id)->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ]);
    }
}
