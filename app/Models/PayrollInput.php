<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollInput extends Model
{
    public const TYPE_BONUS = 'bonus';

    public const TYPE_INCENTIVE = 'incentive';

    public const TYPE_ARREARS = 'arrears';

    public const TYPE_ADDITIONAL_EARNING = 'additional_earning';

    public const TYPE_ADDITIONAL_DEDUCTION = 'additional_deduction';

    public const TYPE_REIMBURSEMENT = 'reimbursement';

    public const TYPE_LOP_ADJUSTMENT = 'lop_adjustment';

    public const TYPE_LOAN_DEDUCTION = 'loan_deduction';

    public const TYPE_SALARY_ADVANCE = 'salary_advance';

    public const TYPE_TDS_ADJUSTMENT = 'tds_adjustment';

    public const TYPES = [
        self::TYPE_BONUS => 'Bonus',
        self::TYPE_INCENTIVE => 'Incentive',
        self::TYPE_ARREARS => 'Arrears',
        self::TYPE_ADDITIONAL_EARNING => 'Additional Earning',
        self::TYPE_ADDITIONAL_DEDUCTION => 'Additional Deduction',
        self::TYPE_REIMBURSEMENT => 'Reimbursement',
        self::TYPE_LOP_ADJUSTMENT => 'LOP Adjustment',
        self::TYPE_LOAN_DEDUCTION => 'Loan Deduction',
        self::TYPE_SALARY_ADVANCE => 'Salary Advance',
        self::TYPE_TDS_ADJUSTMENT => 'TDS Adjustment',
    ];

    public const EARNING_TYPES = [
        self::TYPE_BONUS, self::TYPE_INCENTIVE, self::TYPE_ARREARS,
        self::TYPE_ADDITIONAL_EARNING, self::TYPE_REIMBURSEMENT,
    ];

    public const DEDUCTION_TYPES = [
        self::TYPE_ADDITIONAL_DEDUCTION, self::TYPE_LOP_ADJUSTMENT,
        self::TYPE_LOAN_DEDUCTION, self::TYPE_SALARY_ADVANCE, self::TYPE_TDS_ADJUSTMENT,
    ];

    protected $fillable = ['employee_id', 'payroll_month', 'type', 'amount', 'reason', 'created_by', 'approved_by'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
