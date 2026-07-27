<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasActiveScope;

    protected $fillable = [
        'name', 'code', 'annual_entitlement', 'accrual_frequency', 'is_paid_leave', 'carry_forward_allowed',
        'max_carry_forward', 'encashment_allowed', 'allow_negative_balance', 'half_day_allowed',
        'sandwich_rule_applicable', 'probation_allowed', 'min_days_per_request', 'max_days_per_request',
        'attachment_required', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid_leave' => 'boolean',
            'carry_forward_allowed' => 'boolean',
            'encashment_allowed' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'half_day_allowed' => 'boolean',
            'sandwich_rule_applicable' => 'boolean',
            'probation_allowed' => 'boolean',
            'attachment_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }
}
