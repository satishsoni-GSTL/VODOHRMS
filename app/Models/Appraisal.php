<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appraisal extends Model
{
    public const STATUS_GOALS_DRAFT = 'goals_draft';

    public const STATUS_GOALS_SUBMITTED = 'goals_submitted';

    public const STATUS_GOALS_APPROVED = 'goals_approved';

    public const STATUS_SELF_SUBMITTED = 'self_submitted';

    public const STATUS_MANAGER_SUBMITTED = 'manager_submitted';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_GOALS_DRAFT => 'Setting Goals',
        self::STATUS_GOALS_SUBMITTED => 'Goals Awaiting Approval',
        self::STATUS_GOALS_APPROVED => 'Goals Approved',
        self::STATUS_SELF_SUBMITTED => 'Self Review Done',
        self::STATUS_MANAGER_SUBMITTED => 'Manager Review Done',
        self::STATUS_CLOSED => 'Closed',
    ];

    public const RATINGS = [
        5 => '5 - Outstanding',
        4 => '4 - Exceeds expectations',
        3 => '3 - Meets expectations',
        2 => '2 - Partially meets',
        1 => '1 - Does not meet',
    ];

    protected $fillable = [
        'appraisal_cycle_id', 'employee_id', 'manager_id', 'status', 'goals_return_remarks',
        'self_overall_comments', 'manager_overall_comments', 'self_score', 'manager_score', 'final_rating', 'final_remarks',
        'goals_submitted_at', 'goals_approved_at', 'self_submitted_at', 'manager_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'self_score' => 'float',
            'manager_score' => 'float',
            'final_rating' => 'float',
            'goals_submitted_at' => 'datetime',
            'goals_approved_at' => 'datetime',
            'self_submitted_at' => 'datetime',
            'manager_submitted_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(AppraisalGoal::class)->orderBy('sequence')->orderBy('id');
    }

    /** Manager ratings are shown to the employee only once the manager has submitted. */
    public function managerReviewVisibleToEmployee(): bool
    {
        return in_array($this->status, [self::STATUS_MANAGER_SUBMITTED, self::STATUS_CLOSED], true);
    }

    public static function ratingLabel(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        $band = match (true) {
            $score >= 4.5 => 'Outstanding',
            $score >= 3.5 => 'Exceeds expectations',
            $score >= 2.5 => 'Meets expectations',
            $score >= 1.5 => 'Partially meets',
            default => 'Does not meet',
        };

        return number_format($score, 2)." · {$band}";
    }
}
