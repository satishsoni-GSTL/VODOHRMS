<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FullFinalSettlement extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'resignation_id', 'employee_id', 'pending_salary', 'bonus_incentive', 'reimbursement',
        'leave_encashment', 'other_earnings', 'notice_recovery', 'loan_recovery', 'advance_recovery',
        'tds', 'other_deductions', 'final_amount', 'status', 'calculated_by', 'approved_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function resignation(): BelongsTo
    {
        return $this->belongsTo(Resignation::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recalculateFinalAmount(): void
    {
        $this->final_amount = round(
            $this->pending_salary + $this->bonus_incentive + $this->reimbursement
                + $this->leave_encashment + $this->other_earnings
                - $this->notice_recovery - $this->loan_recovery - $this->advance_recovery
                - $this->tds - $this->other_deductions,
            2
        );
    }
}
