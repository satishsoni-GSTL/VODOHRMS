<?php

namespace App\Notifications;

use App\Models\SharedFileRecipient;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * One-time code a share-link recipient types on the file-share site to prove they own the
 * invited mailbox. Send it with notifyNow() — the recipient is waiting on screen for it.
 */
class SharedFileOtpNotification extends BaseNotification
{
    public function __construct(
        private readonly SharedFileRecipient $recipient,
        private readonly string $otp,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replacements = [
            '{recipient_name}' => $this->recipient->name ?: 'there',
            '{file_title}' => $this->recipient->sharedFile->title,
            '{otp}' => $this->otp,
            '{minutes}' => (string) config('fileshare.otp_ttl_minutes'),
        ];

        $rendered = $this->renderTemplate('shared_file_otp', $replacements, 'Your access code for "{file_title}"', [
            'Hi {recipient_name}, use this code to open the file shared with you:',
            '{otp}',
            'This code expires in {minutes} minutes.',
            "If you didn't request this, you can ignore this email.",
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail;
    }
}
