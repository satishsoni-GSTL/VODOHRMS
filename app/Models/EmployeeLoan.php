<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLoan extends Model implements Approvable
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'loan';
    }

    protected function auditedEvents(): array
    {
        return ['created'];
    }

    public const TYPE_LOAN = 'loan';

    public const TYPE_SALARY_ADVANCE = 'salary_advance';

    public const STATUS_PENDING = 'pending';

    public const STATUS_MANAGER_APPROVED = 'manager_approved';

    public const STATUS_HR_APPROVED = 'hr_approved';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT_BACK = 'sent_back';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_MANAGER_APPROVED => 'Manager Approved',
        self::STATUS_HR_APPROVED => 'HR Approved',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_SENT_BACK => 'Sent Back',
    ];

    protected $fillable = [
        'employee_id', 'type', 'requested_amount', 'reason', 'request_date', 'approved_amount',
        'installments', 'monthly_recovery', 'recovery_start_month', 'outstanding_balance',
        'status', 'approval_instance_id',
    ];

    protected function casts(): array
    {
        return ['request_date' => 'date:Y-m-d'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(EmployeeLoanRecovery::class);
    }

    public function getApprovalModule(): string
    {
        return WorkflowDefinition::MODULE_LOAN;
    }

    public function getApprovalConditionContext(): array
    {
        return [
            'amount' => (float) $this->requested_amount,
            'grade_level' => $this->employee?->grade?->level,
        ];
    }

    public function getRequestingEmployeeId(): int
    {
        return $this->employee_id;
    }

    public function applyApprovalOutcome(string $outcome, ?WorkflowLevel $level = null): void
    {
        $status = match (true) {
            $outcome === 'rejected' => self::STATUS_REJECTED,
            $outcome === 'sent_back' => self::STATUS_SENT_BACK,
            $outcome === 'approved_level' && $level === null => self::STATUS_PENDING,
            $outcome === 'approved_level' && $level->approver_type === WorkflowLevel::APPROVER_REPORTING_MANAGER => self::STATUS_MANAGER_APPROVED,
            $outcome === 'approved_level' => self::STATUS_HR_APPROVED,
            $outcome === 'approved' => self::STATUS_ACTIVE,
            default => $this->status,
        };

        $this->update(['status' => $status]);

        if ($status === self::STATUS_ACTIVE) {
            $this->update([
                'approved_amount' => $this->approved_amount ?? $this->requested_amount,
                'outstanding_balance' => $this->approved_amount ?? $this->requested_amount,
            ]);
        }
    }
}
