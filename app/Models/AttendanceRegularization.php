<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Models\Concerns\Auditable;
use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRegularization extends Model implements Approvable
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'attendance_regularization';
    }

    protected function auditedEvents(): array
    {
        return ['created'];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_MANAGER_APPROVED = 'manager_approved';

    public const STATUS_HR_APPROVED = 'hr_approved';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT_BACK = 'sent_back';

    public const TYPES = [
        'missing_punch' => 'Missing Punch',
        'wrong_in' => 'Wrong Check-In',
        'wrong_out' => 'Wrong Check-Out',
        'wfh' => 'Work From Home',
        'on_duty' => 'On Duty',
        'client_visit' => 'Client Visit',
        'other' => 'Other',
    ];

    protected $fillable = [
        'employee_id', 'attendance_date', 'request_type', 'old_values', 'requested_values',
        'reason', 'attachment_path', 'status', 'approval_instance_id',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'old_values' => 'array',
            'requested_values' => 'array',
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

    public function getApprovalModule(): string
    {
        return WorkflowDefinition::MODULE_ATTENDANCE_REGULARIZATION;
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
            $outcome === 'approved' => self::STATUS_APPROVED,
            $outcome === 'rejected' => self::STATUS_REJECTED,
            $outcome === 'sent_back' => self::STATUS_SENT_BACK,
            $outcome === 'approved_level' && $level === null => self::STATUS_PENDING,
            $outcome === 'approved_level' && $level->approver_type === WorkflowLevel::APPROVER_REPORTING_MANAGER => self::STATUS_MANAGER_APPROVED,
            $outcome === 'approved_level' => self::STATUS_HR_APPROVED,
            default => $this->status,
        };

        $this->update(['status' => $status]);

        if ($status === self::STATUS_APPROVED) {
            app(AttendanceService::class)->applyRegularization($this);
        }
    }
}
