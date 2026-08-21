<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Models\Concerns\Auditable;
use App\Services\WorkFromHomeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkFromHomeRequest extends Model implements Approvable
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'work_from_home';
    }

    protected function auditedEvents(): array
    {
        return ['created'];
    }

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

    protected $fillable = [
        'employee_id', 'from_date', 'to_date', 'reason', 'status', 'approval_instance_id',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    /**
     * Number of working days in the requested range (excludes weekly-off and holidays).
     * Computed on demand rather than stored, so it always reflects the current calendar.
     */
    public function getTotalDaysAttribute(): int
    {
        return count(app(WorkFromHomeService::class)->workingDaysBetween($this->employee, $this->from_date, $this->to_date));
    }

    public function getApprovalModule(): string
    {
        return WorkflowDefinition::MODULE_WORK_FROM_HOME;
    }

    public function getApprovalConditionContext(): array
    {
        return [
            'grade_level' => $this->employee?->grade?->level,
            'department_id' => $this->employee?->department_id,
        ];
    }

    public function getRequestingEmployeeId(): int
    {
        return $this->employee_id;
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

        $this->update(['status' => $status]);

        if ($status === self::STATUS_APPROVED) {
            app(WorkFromHomeService::class)->applyApprovedDays($this);
        }
    }
}
