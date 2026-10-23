<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedFileAccessLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'invited' => 'Invited',
        'code_sent' => 'Code sent',
        'code_failed' => 'Wrong code',
        'verified' => 'Verified',
        'viewed' => 'Viewed',
        'downloaded' => 'Downloaded',
        'commented' => 'Commented',
        'revoked' => 'Access revoked',
        'restored' => 'Access restored',
    ];

    protected $fillable = ['shared_file_id', 'recipient_id', 'user_id', 'action', 'ip_address', 'user_agent'];

    public function sharedFile(): BelongsTo
    {
        return $this->belongsTo(SharedFile::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(SharedFileRecipient::class, 'recipient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
