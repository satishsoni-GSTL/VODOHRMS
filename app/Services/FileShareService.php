<?php

namespace App\Services;

use App\Models\SharedFile;
use App\Models\SharedFileAccessLog;
use App\Models\SharedFileComment;
use App\Models\SharedFileRecipient;
use App\Models\User;
use App\Notifications\SharedFileCommentNotification;
use App\Notifications\SharedFileInvitationNotification;
use App\Notifications\SharedFileOtpNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/**
 * Everything behind "share a file": invitations, emailed access codes, comment threads and
 * the access log. Used by the Filament resource (owner side) and FileShareController
 * (recipient side on the file-share domain).
 */
class FileShareService
{
    /** Fills mime_type/size from the stored upload. */
    public function syncFileMeta(SharedFile $file): void
    {
        $disk = Storage::disk(config('fileshare.disk'));

        if ($disk->exists($file->file_path)) {
            $file->forceFill([
                'mime_type' => $disk->mimeType($file->file_path) ?: null,
                'size' => $disk->size($file->file_path),
            ])->saveQuietly();
        }
    }

    public function invite(SharedFileRecipient $recipient, ?User $by = null): void
    {
        $recipient->notify(new SharedFileInvitationNotification($recipient));
        $recipient->forceFill(['invited_at' => now()])->save();

        $this->log($recipient->sharedFile, 'invited', $recipient, $by);
    }

    public function inviteUninvited(SharedFile $file, ?User $by = null): int
    {
        $pending = $file->recipients()->whereNull('invited_at')->whereNull('revoked_at')->get();

        $pending->each(fn (SharedFileRecipient $recipient) => $this->invite($recipient->setRelation('sharedFile', $file), $by));

        return $pending->count();
    }

    public function revoke(SharedFileRecipient $recipient, ?User $by = null): void
    {
        $recipient->forceFill(['revoked_at' => now(), 'otp_hash' => null])->save();
        $this->log($recipient->sharedFile, 'revoked', $recipient, $by);
    }

    public function restore(SharedFileRecipient $recipient, ?User $by = null): void
    {
        $recipient->forceFill(['revoked_at' => null])->save();
        $this->log($recipient->sharedFile, 'restored', $recipient, $by);
    }

    /**
     * Emails a fresh 6-digit code. Returns null on success, or the seconds to wait when the
     * recipient has asked for too many codes recently.
     */
    public function sendOtp(SharedFileRecipient $recipient): ?int
    {
        $key = 'fileshare-otp:'.$recipient->id;

        if (RateLimiter::tooManyAttempts($key, config('fileshare.otp_max_sends'))) {
            return RateLimiter::availableIn($key);
        }

        RateLimiter::hit($key, 600);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $recipient->forceFill([
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(config('fileshare.otp_ttl_minutes')),
            'otp_attempts' => 0,
        ])->save();

        $recipient->notifyNow(new SharedFileOtpNotification($recipient, $otp));

        $this->log($recipient->sharedFile, 'code_sent', $recipient);

        return null;
    }

    public function verifyOtp(SharedFileRecipient $recipient, string $code): bool
    {
        $valid = $recipient->otp_hash !== null
            && $recipient->otp_expires_at?->isFuture()
            && $recipient->otp_attempts < config('fileshare.otp_max_attempts')
            && Hash::check($code, $recipient->otp_hash);

        if (! $valid) {
            if ($recipient->otp_hash !== null) {
                $recipient->increment('otp_attempts');
            }

            $this->log($recipient->sharedFile, 'code_failed', $recipient);

            return false;
        }

        $recipient->forceFill(['otp_hash' => null, 'otp_expires_at' => null, 'otp_attempts' => 0])->save();
        $this->log($recipient->sharedFile, 'verified', $recipient);

        return true;
    }

    public function recordView(SharedFileRecipient $recipient): void
    {
        $recipient->forceFill([
            'last_accessed_at' => now(),
            'access_count' => $recipient->access_count + 1,
        ])->save();

        $this->log($recipient->sharedFile, 'viewed', $recipient);
    }

    /**
     * Posts a comment as either a share-link recipient or a portal user. Replies always hang
     * off the thread's root comment, and everyone in the conversation (except the author)
     * is emailed.
     */
    public function addComment(
        SharedFile $file,
        string $body,
        ?SharedFileComment $parent = null,
        ?SharedFileRecipient $recipient = null,
        ?User $user = null,
    ): SharedFileComment {
        $root = $parent?->parent_id ? $parent->parent : $parent;

        $comment = $file->comments()->create([
            'parent_id' => $root?->id,
            'recipient_id' => $recipient?->id,
            'user_id' => $recipient ? $recipient->user_id : $user?->id,
            'author_name' => $recipient ? $recipient->displayName() : $user->name,
            'author_email' => $recipient ? $recipient->email : $user->email,
            'body' => trim($body),
        ]);

        $this->log($file, 'commented', $recipient, $recipient ? null : $user);

        $comment->setRelation('sharedFile', $file);
        $this->notifyParticipants($comment, $root, $recipient, $user);

        return $comment;
    }

    private function notifyParticipants(SharedFileComment $comment, ?SharedFileComment $root, ?SharedFileRecipient $authorRecipient, ?User $authorUser): void
    {
        $file = $comment->sharedFile;
        $notification = new SharedFileCommentNotification($comment);

        // The owner hears about every comment they didn't write themselves.
        if ($authorRecipient !== null || $authorUser?->id !== $file->owner_id) {
            $file->owner?->notify($notification);
        }

        $this->recipientsToNotify($file, $root, fromPortal: $authorRecipient === null)
            ->reject(fn (SharedFileRecipient $r) => $r->id === $authorRecipient?->id)
            ->each(fn (SharedFileRecipient $r) => $r->notify($notification));
    }

    /**
     * Top-level comment from the portal → every active recipient; a reply → the people
     * already in that thread; a top-level comment from a recipient → nobody else (it's
     * addressed to the owner).
     *
     * @return Collection<int, SharedFileRecipient>
     */
    private function recipientsToNotify(SharedFile $file, ?SharedFileComment $root, bool $fromPortal): Collection
    {
        $active = $file->recipients()->whereNull('revoked_at');

        if ($root === null) {
            return $fromPortal ? $active->get() : collect();
        }

        $ids = $root->replies()->pluck('recipient_id')->push($root->recipient_id)->filter()->unique();

        return $active->whereIn('id', $ids)->get();
    }

    public function log(SharedFile $file, string $action, ?SharedFileRecipient $recipient = null, ?User $user = null): void
    {
        $request = app()->bound('request') ? request() : null;

        SharedFileAccessLog::create([
            'shared_file_id' => $file->id,
            'recipient_id' => $recipient?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }
}
