<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionalHolidayClaim extends Model
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'attendance';
    }

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_CLAIMED => 'Claimed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = ['employee_id', 'holiday_id', 'status', 'reason', 'claimed_by', 'cancelled_at'];

    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class);
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }
}
