<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Resignation extends Model implements Approvable
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'resignation';
    }

    protected function auditedEvents(): array
    {
        return ['created'];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_MANAGER_APPROVED = 'manager_approved';

    public const STATUS_HR_APPROVED = 'hr_approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_MANAGER_APPROVED => 'Manager Approved',
        self::STATUS_HR_APPROVED => 'HR Approved (Accepted)',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_WITHDRAWN => 'Withdrawn',
    ];

    protected $fillable = [
        'employee_id', 'resignation_date', 'reason', 'requested_last_working_date', 'notice_period_days',
        'manager_comments', 'hr_comments', 'approved_last_working_date', 'status', 'approval_instance_id',
    ];

    protected function casts(): array
    {
        return [
            'resignation_date' => 'date:Y-m-d',
            'requested_last_working_date' => 'date:Y-m-d',
            'approved_last_working_date' => 'date:Y-m-d',
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

    public function exitClearances(): HasMany
    {
        return $this->hasMany(ExitClearance::class);
    }

    public function fullFinalSettlement(): HasOne
    {
        return $this->hasOne(FullFinalSettlement::class);
    }

    public function getApprovalModule(): string
    {
        return WorkflowDefinition::MODULE_RESIGNATION;
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
        $status = match (true) {
            $outcome === 'rejected' => self::STATUS_REJECTED,
            $outcome === 'sent_back' => self::STATUS_REJECTED,
            $outcome === 'approved_level' && $level === null => self::STATUS_PENDING,
            $outcome === 'approved_level' => self::STATUS_MANAGER_APPROVED,
            $outcome === 'approved' => self::STATUS_HR_APPROVED,
            default => $this->status,
        };

        $data = ['status' => $status];

        if ($status === self::STATUS_HR_APPROVED) {
            $data['approved_last_working_date'] = $this->approved_last_working_date ?? $this->requested_last_working_date;
        }

        $this->update($data);
    }
}
