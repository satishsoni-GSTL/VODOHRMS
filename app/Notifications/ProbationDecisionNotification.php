<?php

namespace App\Notifications;

use App\Models\ProbationReview;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/** Tells the employee the final outcome of their probation review, with the letter attached. */
class ProbationDecisionNotification extends BaseNotification
{
    public function __construct(private readonly ProbationReview $review) {}

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable);
    }

    private function isConfirmed(): bool
    {
        return $this->review->recommendation === ProbationReview::RECOMMEND_CONFIRM;
    }

    public function toPush(object $notifiable): ?array
    {
        $date = $this->review->effective_date->format('d M Y');

        return [
            'title' => $this->isConfirmed() ? 'Congratulations on your confirmation!' : 'Probation extended',
            'body' => $this->isConfirmed()
                ? "Your employment has been confirmed with effect from {$date}."
                : "Your probation period has been extended until {$date}.",
            'data' => ['screen' => 'home'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->review->employee;
        $replacements = [
            '{employee_name}' => trim(preg_replace('/\s+/', ' ', $employee->full_name)),
            '{effective_date}' => $this->review->effective_date->format('d M Y'),
            '{extension_days}' => (string) $this->review->extension_days,
        ];

        $rendered = $this->isConfirmed()
            ? $this->renderTemplate('probation_confirmed', $replacements, 'Confirmation of employment', [
                'Dear {employee_name},',
                'We are pleased to confirm your employment with effect from {effective_date}.',
                'Your confirmation letter is attached. Congratulations, and thank you for your contribution.',
            ])
            : $this->renderTemplate('probation_extended', $replacements, 'Extension of probation period', [
                'Dear {employee_name},',
                'Following your probation review, your probation period has been extended by {extension_days} days, until {effective_date}.',
                'Your letter is attached. Please speak with your reporting manager about the areas to focus on.',
            ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        $path = $this->review->letter_path;

        if ($path && Storage::disk('local')->exists($path)) {
            $mail->attach(Storage::disk('local')->path($path), [
                'as' => ($this->isConfirmed() ? 'Confirmation-Letter' : 'Probation-Extension-Letter').'.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
