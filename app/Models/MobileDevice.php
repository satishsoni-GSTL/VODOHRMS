<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone signed in to the employee mobile app. The employee logs in once; the app keeps
 * the device token in secure storage and sends it with every API call (see
 * AuthenticateMobileDevice). fcm_token is where push notifications go. Revoking the row
 * signs that phone out.
 */
class MobileDevice extends Model
{
    protected $fillable = [
        'user_id', 'token_hash', 'device_name', 'platform', 'app_version', 'fcm_token', 'fcm_token_updated_at', 'last_used_at', 'last_ip', 'revoked_at',
    ];

    protected $hidden = ['token_hash', 'fcm_token'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
