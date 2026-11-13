<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

/**
 * A new hire's link to the pre-joining portal. The raw token is only ever in the emailed
 * link; we store its SHA-256. Opening it also needs a one-time code sent to the same email.
 */
class PrejoiningInvite extends Model
{
    use Notifiable;

    public const STATUS_SENT = 'sent';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_SENT => 'Invited',
        self::STATUS_SUBMITTED => 'Submitted — to review',
        self::STATUS_APPROVED => 'Reviewed',
        self::STATUS_REVOKED => 'Revoked',
    ];

    protected $fillable = [
        'employee_id', 'email', 'token_hash', 'expires_at', 'status', 'sent_by',
        'last_accessed_at', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_remarks',
    ];

    protected $hidden = ['token_hash', 'otp_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function findByToken(string $token): ?self
    {
        return static::with('employee')->where('token_hash', hash('sha256', $token))->first();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The portal link works (view / verify) while not revoked or expired. */
    public function isUsable(): bool
    {
        return $this->status !== self::STATUS_REVOKED && $this->expires_at->isFuture() && $this->employee !== null;
    }

    /** The new hire can still change their details (not yet submitted, or reopened by HR). */
    public function isEditable(): bool
    {
        return $this->isUsable() && $this->status === self::STATUS_SENT;
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function maskedEmail(): string
    {
        [$local, $domain] = array_pad(explode('@', $this->email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('•', max(mb_strlen($local) - 2, 1)).'@'.$domain;
    }
}
