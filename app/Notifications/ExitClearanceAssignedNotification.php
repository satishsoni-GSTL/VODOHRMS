<?php

namespace App\Notifications;

use App\Models\ExitClearance;
use Illuminate\Notifications\Messages\MailMessage;

class ExitClearanceAssignedNotification extends BaseNotification
{
    public function __construct(private readonly ExitClearance $clearance) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resignation = $this->clearance->resignation;
        $employee = $resignation->employee;
        $department = ucfirst($this->clearance->department);

        $replacements = [
            '{employee_name}' => $employee->full_name,
            '{employee_code}' => $employee->employee_code,
            '{department}' => $department,
            '{last_working_date}' => $resignation->requested_last_working_date->toFormattedDateString(),
        ];

        $rendered = $this->renderTemplate('exit_clearance_assigned', $replacements, 'Exit clearance needed: {department} — {employee_name}', [
            '{employee_name} ({employee_code}) has resigned and requires {department} clearance.',
            'Last working date: {last_working_date}',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action('Review Exit Clearance', url('/admin/exit-clearances'));
    }
}
