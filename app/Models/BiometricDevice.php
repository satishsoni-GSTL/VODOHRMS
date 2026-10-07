<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

class BiometricDevice extends Model
{
    protected $fillable = [
        'name', 'code', 'branch_id', 'location', 'allowed_ips', 'api_token_hash',
        'is_active', 'last_synced_at', 'last_synced_ip',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Generate a new plaintext token, store its hash, and return the plaintext.
     * The plaintext is only ever available at the moment this is called.
     */
    public function issueToken(): string
    {
        $token = Str::random(40);
        $this->update(['api_token_hash' => static::hashToken($token)]);

        return $token;
    }

    public static function findByToken(string $token): ?self
    {
        $hash = static::hashToken($token);

        return static::where('is_active', true)
            ->get()
            ->first(fn (self $device) => hash_equals($device->api_token_hash, $hash));
    }

    /**
     * Whether the sync agent may post from $ip. With no allow-list configured any IP is
     * accepted (legacy behaviour); set the office's public IP(s) to lock a leaked token out.
     */
    public function allowsIp(?string $ip): bool
    {
        $allowed = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $this->allowed_ips))));

        return $allowed === [] || ($ip !== null && IpUtils::checkIp($ip, $allowed));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function punchLogs(): HasMany
    {
        return $this->hasMany(DevicePunchLog::class);
    }
}
