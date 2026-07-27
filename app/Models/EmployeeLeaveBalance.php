<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'year', 'opening_balance', 'credited',
        'adjusted', 'used', 'lapsed', 'encashed', 'closing_balance',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function recalculateClosingBalance(): void
    {
        $this->closing_balance = round(
            $this->opening_balance + $this->credited + $this->adjusted - $this->used - $this->lapsed - $this->encashed,
            2
        );
    }
}
