<?php

namespace App\Notifications;

use App\Contracts\Approvable;
use App\Notifications\Concerns\DescribesApprovalModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class ApprovalOutcomeNotification extends BaseNotification
{
    use DescribesApprovalModule;

    public function __construct(
        private readonly Approvable&Model $requestable,
        private readonly string $outcome,
        private readonly ?string $remarks = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $module = $this->moduleLabel($this->requestable->getApprovalModule());
        $outcomeLabel = match ($this->outcome) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'sent_back' => 'sent back for changes',
            default => $this->outcome,
        };

        $replacements = [
            '{module}' => $module,
            '{outcome}' => $outcomeLabel,
            '{remarks_line}' => $this->remarks ? "Remarks: {$this->remarks}" : '',
        ];

        $rendered = $this->renderTemplate('approval_outcome', $replacements, 'Your {module} request has been {outcome}', [
            'Your {module} request has been {outcome}.',
            '{remarks_line}',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action('View Request', url($this->moduleRoute($this->requestable->getApprovalModule())));
    }
}
