<?php

namespace App\Notifications;

use App\Models\PrejoiningInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invitation to the pre-joining portal (sent immediately, not queued — it carries the link). */
class PrejoiningInviteNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PrejoiningInvite $invite, private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->invite->employee;
        $company = $employee->company?->name ?? config('app.name');
        $joining = $employee->date_of_joining?->format('d M Y');

        return (new MailMessage)
            ->subject("Welcome to {$company} — complete your joining details")
            ->greeting("Dear {$employee->first_name},")
            ->line("Welcome aboard! ".($joining ? "We look forward to having you join us on {$joining}. " : '')
                .'Before your first day, please fill in your personal, address, emergency-contact, statutory (PAN / Aadhaar) and bank details, and upload your documents.')
            ->action('Complete my joining details', $this->url)
            ->line('For your security, we will email a one-time code to this address when you open the link. Please do not forward this email.')
            ->line('The link is valid until '.$this->invite->expires_at->format('d M Y').'.');
    }
}
