<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** History of who held which position when - the audit trail for transfers and promotions. */
class PositionAssignment extends Model
{
    public const REASONS = [
        'joining' => 'New Joining',
        'transfer' => 'Transfer',
        'promotion' => 'Promotion',
        'redesignation' => 'Re-designation',
        'exit' => 'Exit',
        'other' => 'Other',
    ];

    protected $fillable = ['position_id', 'employee_id', 'start_date', 'end_date', 'reason', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class)->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
