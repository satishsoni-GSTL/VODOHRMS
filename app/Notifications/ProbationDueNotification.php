<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Notifications\Messages\MailMessage;

/** Reminds the reporting manager / HR that an employee's probation is ending and needs a review. */
class ProbationDueNotification extends BaseNotification
{
    public function __construct(private readonly Employee $employee) {}

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable);
    }

    private function name(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->employee->full_name));
    }

    private function dueText(): string
    {
        $end = $this->employee->probationEndDate();
        $days = (int) now()->startOfDay()->diffInDays($end, false);

        return match (true) {
            $days > 0 => "ends on {$end->format('d M Y')} ({$days} day".($days === 1 ? '' : 's').' left)',
            $days === 0 => 'ends today',
            default => "ended on {$end->format('d M Y')} and is overdue for review",
        };
    }

    public function toPush(object $notifiable): ?array
    {
        return [
            'title' => 'Probation review due',
            'body' => "{$this->name()} ({$this->employee->employee_code}): probation {$this->dueText()}.",
            'data' => ['screen' => 'home'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rendered = $this->renderTemplate('probation_due', [
            '{employee_name}' => $this->name(),
            '{employee_code}' => $this->employee->employee_code,
            '{due_text}' => $this->dueText(),
        ], 'Probation review due: {employee_name}', [
            'The probation period of {employee_name} ({employee_code}) {due_text}.',
            'Please complete the probation review and recommend confirmation or an extension.',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action('Open Probation Reviews', url('/admin/probation-reviews'));
    }
}
