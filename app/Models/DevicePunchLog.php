<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePunchLog extends Model
{
    const UPDATED_AT = null;

    public const STATUS_PENDING = 'pending';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_MATCHED => 'Matched',
        self::STATUS_UNMATCHED => 'Unmatched',
        self::STATUS_DUPLICATE => 'Duplicate',
    ];

    protected $fillable = [
        'biometric_device_id', 'device_user_id', 'punch_time', 'punch_type',
        'raw_payload', 'employee_id', 'status', 'attendance_punch_id',
    ];

    protected function casts(): array
    {
        return [
            'punch_time' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendancePunch(): BelongsTo
    {
        return $this->belongsTo(AttendancePunch::class);
    }
}
