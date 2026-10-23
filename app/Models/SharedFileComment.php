<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharedFileComment extends Model
{
    protected $fillable = [
        'shared_file_id', 'parent_id', 'recipient_id', 'user_id', 'author_name', 'author_email', 'body',
    ];

    public function sharedFile(): BelongsTo
    {
        return $this->belongsTo(SharedFile::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(SharedFileRecipient::class, 'recipient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Posted from inside the HRMS portal (owner or HR) rather than via a share link. */
    public function isFromPortal(): bool
    {
        return $this->user_id !== null && $this->recipient_id === null;
    }
}
