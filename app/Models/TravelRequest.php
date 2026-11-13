<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelRequest extends Model implements Approvable
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'travel';
    }

    protected function auditedEvents(): array
    {
        return ['created'];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT_BACK = 'sent_back';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_SENT_BACK => 'Sent Back',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_CLOSED => 'Closed',
    ];

    public const TYPES = ['domestic' => 'Domestic', 'international' => 'International'];

    public const MODES = ['air' => 'Air', 'train' => 'Train', 'bus' => 'Bus', 'car' => 'Car / Cab', 'other' => 'Other'];

    protected $fillable = [
        'request_number', 'employee_id', 'purpose', 'travel_type', 'travel_mode', 'from_location', 'to_location',
        'departure_date', 'return_date', 'accommodation_required', 'estimated_cost', 'advance_requested',
        'advance_paid', 'advance_paid_on', 'advance_reference', 'advance_paid_by',
        'status', 'approval_instance_id', 'closed_at', 'closure_remarks',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date:Y-m-d',
            'return_date' => 'date:Y-m-d',
            'advance_paid_on' => 'date:Y-m-d',
            'closed_at' => 'datetime',
            'accommodation_required' => 'boolean',
            'estimated_cost' => 'decimal:2',
            'advance_requested' => 'decimal:2',
            'advance_paid' => 'decimal:2',
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

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class);
    }

    public function getRouteLabelAttribute(): string
    {
        return "{$this->from_location} → {$this->to_location}";
    }

    public function getDaysAttribute(): int
    {
        return $this->departure_date && $this->return_date
            ? (int) $this->departure_date->diffInDays($this->return_date) + 1
            : 0;
    }

    /** Advance paid but not yet netted off a paid expense claim (negative never). */
    public function outstandingAdvance(): float
    {
        $adjusted = (float) $this->expenseClaims()->where('status', ExpenseClaim::STATUS_PAID)->sum('advance_adjusted');

        return max(round((float) $this->advance_paid - $adjusted, 2), 0.0);
    }

    public function getApprovalModule(): string
    {
        return WorkflowDefinition::MODULE_TRAVEL;
    }

    public function getApprovalConditionContext(): array
    {
        return [
            'advance_requested' => (float) $this->advance_requested,
            'estimated_cost' => (float) $this->estimated_cost,
            'travel_type' => $this->travel_type,
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
        $this->update(['status' => match ($outcome) {
            'approved' => self::STATUS_APPROVED,
            'rejected' => self::STATUS_REJECTED,
            'sent_back' => self::STATUS_SENT_BACK,
            'approved_level' => self::STATUS_PENDING,
            default => $this->status,
        }]);
    }
}
