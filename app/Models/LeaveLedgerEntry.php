<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveLedgerEntry extends Model
{
    public const TYPE_OPENING = 'opening';

    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_LAPSE = 'lapse';

    public const TYPE_ENCASHMENT = 'encashment';

    protected $fillable = [
        'employee_id', 'leave_type_id', 'entry_date', 'type', 'days',
        'balance_after', 'reference_type', 'reference_id', 'remarks',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date:Y-m-d'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
