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
        return $this->withPush($notifiable);
    }

    public function toPush(object $notifiable): ?array
    {
        $module = $this->requestable->getApprovalModule();
        $label = $this->moduleLabel($module);

        $title = match ($this->outcome) {
            'approved' => "{$label} approved",
            'rejected' => "{$label} rejected",
            'sent_back' => "{$label} sent back for changes",
            default => "{$label} updated",
        };

        return [
            'title' => $title,
            'body' => $this->remarks ? "Remarks: {$this->remarks}" : "Your {$label} request has been {$this->outcome}.",
            'data' => [
                'screen' => match ($module) {
                    \App\Models\WorkflowDefinition::MODULE_EXPENSE => 'expenses',
                    \App\Models\WorkflowDefinition::MODULE_LOAN => 'loans',
                    \App\Models\WorkflowDefinition::MODULE_RESIGNATION => 'home',
                    default => 'requests',
                },
                'module' => $module,
            ],
        ];
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
