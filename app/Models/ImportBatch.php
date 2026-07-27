<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_ATTENDANCE = 'attendance';

    protected $fillable = [
        'importable_type', 'file_name', 'file_path', 'uploaded_by', 'uploaded_at',
        'total_rows', 'success_rows', 'failed_rows', 'status',
    ];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'batch_id');
    }
}
