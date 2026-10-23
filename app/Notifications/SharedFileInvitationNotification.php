<?php

namespace App\Notifications;

use App\Models\SharedFileRecipient;
use Illuminate\Notifications\Messages\MailMessage;

class SharedFileInvitationNotification extends BaseNotification
{
    public function __construct(
        private readonly SharedFileRecipient $recipient,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $file = $this->recipient->sharedFile;

        $replacements = [
            '{recipient_name}' => $this->recipient->name ?: 'there',
            '{owner_name}' => $file->owner->name,
            '{file_title}' => $file->title,
            '{file_name}' => $file->file_name,
            '{message}' => (string) $file->description,
            '{expiry_line}' => $file->expires_at ? 'This link expires on '.$file->expires_at->format('d M Y, h:i A').'.' : '',
        ];

        $rendered = $this->renderTemplate('shared_file_invitation', $replacements, '{owner_name} shared "{file_title}" with you', [
            'Hi {recipient_name}, {owner_name} has shared a file with you.',
            'File: {file_title} ({file_name})',
            '{message}',
            'You will be asked to confirm a one-time code sent to this email address before the file opens.',
            '{expiry_line}',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action('Open File', $this->recipient->shareUrl());
    }
}
