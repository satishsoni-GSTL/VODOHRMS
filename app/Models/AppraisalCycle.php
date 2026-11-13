<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalCycle extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_GOAL_SETTING = 'goal_setting';

    public const STATUS_REVIEW = 'review';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_GOAL_SETTING => 'Goal Setting',
        self::STATUS_REVIEW => 'Review',
        self::STATUS_CLOSED => 'Closed',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'min_goals' => 3];

    protected $fillable = ['name', 'company_id', 'period_start', 'period_end', 'goal_setting_due', 'review_due', 'min_goals', 'status'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'goal_setting_due' => 'date:Y-m-d',
            'review_due' => 'date:Y-m-d',
            'min_goals' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class);
    }
}
