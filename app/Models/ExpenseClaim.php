<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExpenseClaim extends Model implements Approvable
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'expense';
    }

    protected function auditedEvents(): array
    {
        return ['created'];
    }

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PENDING_FINANCE = 'pending_finance';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT_BACK = 'sent_back';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_PENDING_FINANCE => 'Pending Finance',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_SENT_BACK => 'Sent Back',
        self::STATUS_PAID => 'Paid',
    ];

    protected $fillable = [
        'claim_number', 'employee_id', 'claim_date', 'project_client', 'status',
        'approval_instance_id', 'total_requested_amount', 'total_approved_amount',
    ];

    protected function casts(): array
    {
        return ['claim_date' => 'date:Y-m-d'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseClaimLine::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(ExpensePayment::class);
    }

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    public function recalculateTotals(): void
    {
        $this->total_requested_amount = $this->lines()->sum('requested_amount');
        $this->total_approved_amount = $this->lines()->sum('approved_amount');
    }

    public function getApprovalModule(): string
    {
        return WorkflowDefinition::MODULE_EXPENSE;
    }

    public function getApprovalConditionContext(): array
    {
        return [
            'amount' => (float) $this->total_requested_amount,
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
            'approved_level' => $level === null ? self::STATUS_SUBMITTED : self::STATUS_PENDING_FINANCE,
            default => $this->status,
        };

        $this->update(['status' => $status]);

        if ($status === self::STATUS_APPROVED) {
            $this->lines()->whereNull('approved_amount')->get()->each(
                fn (ExpenseClaimLine $line) => $line->update(['approved_amount' => $line->requested_amount])
            );
            $this->recalculateTotals();
            $this->save();
        }
    }
}
