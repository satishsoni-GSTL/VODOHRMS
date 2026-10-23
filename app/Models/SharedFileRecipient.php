<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * One person a SharedFile is shared with. The link token is unique per recipient, so the
 * emailed access code always goes to the address the owner typed — a forwarded link is
 * useless without access to that mailbox.
 */
class SharedFileRecipient extends Model
{
    use Notifiable;

    protected $fillable = [
        'shared_file_id', 'user_id', 'email', 'name', 'can_comment', 'invited_at',
        'last_accessed_at', 'access_count', 'revoked_at',
    ];

    protected $hidden = ['token', 'token_hash', 'otp_hash'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'can_comment' => 'boolean',
            'otp_expires_at' => 'datetime',
            'invited_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SharedFileRecipient $recipient) {
            $plain = Str::random(48);
            $recipient->token = $plain;
            $recipient->token_hash = hash('sha256', $plain);
        });

        static::saving(function (SharedFileRecipient $recipient) {
            $recipient->email = Str::lower(trim($recipient->email));
        });
    }

    public static function findByToken(string $token): ?self
    {
        return static::with('sharedFile.owner')->where('token_hash', hash('sha256', $token))->first();
    }

    public function sharedFile(): BelongsTo
    {
        return $this->belongsTo(SharedFile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SharedFileComment::class, 'recipient_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isInternal(): bool
    {
        return $this->user_id !== null;
    }

    public function shareUrl(): string
    {
        $base = config('fileshare.url') ?: url('/');

        return $base.'/s/'.$this->token;
    }

    public function displayName(): string
    {
        return $this->name ?: $this->email;
    }

    /** j***@example.com — shown on the verify screen without revealing the full address. */
    public function maskedEmail(): string
    {
        [$local, $domain] = array_pad(explode('@', $this->email, 2), 2, '');

        return Str::substr($local, 0, 1).str_repeat('*', max(Str::length($local) - 1, 3)).'@'.$domain;
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
