<?php

namespace App\Notifications;

use App\Models\PrejoiningInvite;
use App\Services\PrejoiningService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrejoiningOtpNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PrejoiningInvite $invite, private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your verification code: {$this->code}")
            ->line("Use this code to open your joining details form: **{$this->code}**")
            ->line('It expires in '.PrejoiningService::OTP_TTL_MINUTES.' minutes. If you did not request it, you can ignore this email.');
    }
}
