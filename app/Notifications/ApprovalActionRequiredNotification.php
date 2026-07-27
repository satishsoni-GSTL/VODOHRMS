<?php

namespace App\Notifications;

use App\Contracts\Approvable;
use App\Models\Employee;
use App\Notifications\Concerns\DescribesApprovalModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class ApprovalActionRequiredNotification extends BaseNotification
{
    use DescribesApprovalModule;

    public function __construct(
        private readonly Approvable&Model $requestable,
        private readonly Employee $requestingEmployee,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $module = $this->moduleLabel($this->requestable->getApprovalModule());

        $replacements = [
            '{module}' => $module,
            '{employee_name}' => $this->requestingEmployee->full_name,
            '{employee_code}' => $this->requestingEmployee->employee_code,
        ];

        $rendered = $this->renderTemplate('approval_action_required', $replacements, 'Approval required: {module} — {employee_name}', [
            '{employee_name} ({employee_code}) has submitted a {module} request that needs your action.',
            'Please review and act on this request at your earliest convenience.',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action('Review Pending Approvals', url('/admin/pending-approvals'));
    }
}
