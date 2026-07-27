<?php

namespace App\Notifications;

use App\Models\FullFinalSettlement;
use Illuminate\Notifications\Messages\MailMessage;

class FnfSettlementNotification extends BaseNotification
{
    public function __construct(
        private readonly FullFinalSettlement $settlement,
        private readonly string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eventLabel = $this->event === 'paid'
            ? 'Your full & final settlement has been paid'
            : 'Your full & final settlement has been approved';

        $replacements = [
            '{event_label}' => $eventLabel,
            '{final_amount}' => number_format((float) $this->settlement->final_amount, 2),
        ];

        $rendered = $this->renderTemplate('fnf_settlement', $replacements, '{event_label}', [
            '{event_label}.',
            'Final settlement amount: {final_amount}',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action('View Details', url('/admin/full-final-settlements'));
    }
}
