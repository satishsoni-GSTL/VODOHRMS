<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitClearance extends Model
{
    public const DEPARTMENTS = ['manager', 'it', 'admin', 'finance', 'hr'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['resignation_id', 'department', 'status', 'remarks', 'cleared_by', 'cleared_at'];

    protected function casts(): array
    {
        return ['cleared_at' => 'datetime'];
    }

    public function resignation(): BelongsTo
    {
        return $this->belongsTo(Resignation::class);
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }
}
