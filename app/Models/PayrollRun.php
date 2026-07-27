<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_REOPENED = 'reopened';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_CALCULATED => 'Calculated',
        self::STATUS_REVIEWED => 'Reviewed',
        self::STATUS_FINALIZED => 'Finalized',
        self::STATUS_LOCKED => 'Locked',
        self::STATUS_REOPENED => 'Reopened',
    ];

    protected $fillable = ['payroll_month', 'company_id', 'status', 'locked_at', 'locked_by', 'reopened_reason'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(PayrollRunEmployee::class);
    }

    public function isEditable(): bool
    {
        return ! in_array($this->status, [self::STATUS_FINALIZED, self::STATUS_LOCKED], true);
    }
}
