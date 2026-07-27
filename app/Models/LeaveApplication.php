<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Models\Concerns\Auditable;
use App\Services\LeaveBalanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model implements Approvable
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'leave';
    }

    protected function auditedEvents(): array
    {
        return ['created'];
    }

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT_BACK = 'sent_back';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id', 'leave_type_id', 'from_date', 'to_date', 'days', 'is_half_day',
        'half_day_session', 'reason', 'attachment_path', 'status', 'approval_instance_id',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'is_half_day' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    public function getApprovalModule(): string
    {
        return WorkflowDefinition::MODULE_LEAVE;
    }

    public function getApprovalConditionContext(): array
    {
        return [
            'days' => (float) $this->days,
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
            app(LeaveBalanceService::class)->debitForApprovedLeave($this);
        }

        if ($status === self::STATUS_REJECTED && $this->getOriginal('status') === self::STATUS_APPROVED) {
            app(LeaveBalanceService::class)->reverseForCancelledLeave($this);
        }
    }
}
