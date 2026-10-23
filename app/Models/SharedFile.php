<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * A file an HRMS user shares with internal or external people. Each recipient gets their own
 * link on the file-share domain (config('fileshare.url')) and must verify an emailed code
 * before they can view, download or comment.
 */
class SharedFile extends Model
{
    use Auditable;

    /**
     * MIME types shown inline on the share page. Anything else (HTML, SVG, Office…) is
     * download-only so uploaded content can never run script on the share domain.
     */
    private const PREVIEW_KINDS = [
        'application/pdf' => 'pdf',
        'image/png' => 'image',
        'image/jpeg' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        'video/mp4' => 'video',
        'video/webm' => 'video',
        'audio/mpeg' => 'audio',
        'audio/wav' => 'audio',
        'text/plain' => 'text',
        'text/csv' => 'text',
    ];

    protected $fillable = [
        'owner_id', 'title', 'description', 'file_path', 'file_name', 'mime_type', 'size',
        'allow_download', 'is_active', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'allow_download' => 'boolean',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (SharedFile $file) {
            Storage::disk(config('fileshare.disk'))->delete($file->file_path);
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SharedFileRecipient::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SharedFileComment::class);
    }

    public function rootComments(): HasMany
    {
        return $this->comments()->whereNull('parent_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(SharedFileAccessLog::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAvailable(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    /** 'pdf' | 'image' | 'video' | 'audio' | 'text' | null */
    public function previewKind(): ?string
    {
        return self::PREVIEW_KINDS[strtolower((string) $this->mime_type)] ?? null;
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
