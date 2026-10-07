<?php

namespace App\Notifications;

use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;

class PayslipReadyNotification extends BaseNotification
{
    public function __construct(private readonly PayrollRun $run) {}

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable);
    }

    public function toPush(object $notifiable): ?array
    {
        $month = Carbon::createFromFormat('Y-m', $this->run->payroll_month)->format('F Y');

        return [
            'title' => "Payslip ready — {$month}",
            'body' => "Your salary slip for {$month} is available in the app.",
            'data' => ['screen' => 'payslips'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $monthLabel = Carbon::createFromFormat('Y-m', $this->run->payroll_month)->format('F Y');

        $replacements = ['{month}' => $monthLabel];

        $rendered = $this->renderTemplate('payslip_ready', $replacements, 'Payslip ready — {month}', [
            'Your payslip for {month} has been finalized and is now available for download.',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action('Download Payslip', url('/admin/my-payslips'));
    }
}
