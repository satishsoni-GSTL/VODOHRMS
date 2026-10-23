<?php

namespace App\Notifications;

use App\Filament\Resources\SharedFileResource;
use App\Models\SharedFileComment;
use App\Models\SharedFileRecipient;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * New comment/reply on a shared file. Recipients get their share link (they verify again if
 * their session lapsed); portal users (the owner / HR) get the file's page in the HRMS.
 */
class SharedFileCommentNotification extends BaseNotification
{
    public function __construct(
        private readonly SharedFileComment $comment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $file = $this->comment->sharedFile;

        $replacements = [
            '{author_name}' => $this->comment->author_name,
            '{comment_kind}' => $this->comment->parent_id ? 'reply' : 'comment',
            '{file_title}' => $file->title,
            '{comment_excerpt}' => Str::limit($this->comment->body, 300),
        ];

        $rendered = $this->renderTemplate('shared_file_comment', $replacements, 'New {comment_kind} on "{file_title}"', [
            '{author_name} posted a {comment_kind} on "{file_title}":',
            '"{comment_excerpt}"',
        ]);

        $mail = (new MailMessage)->subject($rendered['subject']);

        foreach ($rendered['lines'] as $line) {
            $mail->line($line);
        }

        $url = $notifiable instanceof SharedFileRecipient
            ? $notifiable->shareUrl().'#comments'
            : SharedFileResource::getUrl('view', ['record' => $file], panel: 'admin');

        return $mail->action('View Conversation', $url);
    }
}
