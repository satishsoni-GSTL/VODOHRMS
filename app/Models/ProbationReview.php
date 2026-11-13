<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Models\Concerns\Auditable;
use App\Services\ProbationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProbationReview extends Model implements Approvable
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'probation_confirmation';
    }

    protected function auditedEvents(): array
    {
        return ['created'];
    }

    public const RECOMMEND_CONFIRM = 'confirm';

    public const RECOMMEND_EXTEND = 'extend';

    public const RECOMMENDATIONS = [
        self::RECOMMEND_CONFIRM => 'Confirm',
        self::RECOMMEND_EXTEND => 'Extend probation',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT_BACK = 'sent_back';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_SENT_BACK => 'Sent Back',
    ];

    public const RATINGS = [
        5 => '5 - Outstanding',
        4 => '4 - Exceeds expectations',
        3 => '3 - Meets expectations',
        2 => '2 - Needs improvement',
        1 => '1 - Unsatisfactory',
    ];

    protected $fillable = [
        'employee_id', 'probation_end_date', 'recommendation', 'extension_days', 'effective_date',
        'rating', 'performance_summary', 'improvement_areas', 'submitted_by', 'status',
        'approval_instance_id', 'decided_at', 'letter_path',
    ];

    protected function casts(): array
    {
        return [
            'probation_end_date' => 'date:Y-m-d',
            'effective_date' => 'date:Y-m-d',
            'decided_at' => 'datetime',
            'rating' => 'integer',
            'extension_days' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    public function getRecommendationLabelAttribute(): string
    {
        $label = self::RECOMMENDATIONS[$this->recommendation] ?? $this->recommendation;

        return $this->recommendation === self::RECOMMEND_EXTEND && $this->extension_days
            ? "{$label} by {$this->extension_days} days"
            : $label;
    }

    public function getApprovalModule(): string
    {
        return WorkflowDefinition::MODULE_PROBATION_CONFIRMATION;
    }

    public function getApprovalConditionContext(): array
    {
        return [
            'grade_level' => $this->employee?->grade?->level,
            'department_id' => $this->employee?->department_id,
            'recommendation' => $this->recommendation,
        ];
    }

    /** The employee under review — approvers (HR, etc.) are resolved relative to them. */
    public function getRequestingEmployeeId(): int
    {
        return $this->employee_id;
    }

    /**
     * Approved / rejected / sent-back notices go to whoever submitted the review (the
     * manager), not to the employee under review. The employee is told separately, and only
     * once the decision is final (ProbationService::applyApproved).
     */
    public function approvalOutcomeRecipient(): ?Employee
    {
        return $this->submitter?->employee;
    }

    public function applyApprovalOutcome(string $outcome, ?WorkflowLevel $level = null): void
    {
        $status = match ($outcome) {
            'approved' => self::STATUS_APPROVED,
            'rejected' => self::STATUS_REJECTED,
            'sent_back' => self::STATUS_SENT_BACK,
            'approved_level' => self::STATUS_PENDING,
            default => $this->status,
        };

        $this->update([
            'status' => $status,
            'decided_at' => in_array($status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true) ? now() : null,
        ]);

        if ($status === self::STATUS_APPROVED) {
            app(ProbationService::class)->applyApproved($this);
        }
    }
}
