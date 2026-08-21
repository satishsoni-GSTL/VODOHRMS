<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_HALF_DAY = 'half_day';

    public const STATUS_WFH = 'wfh';

    public const STATUS_ON_DUTY = 'on_duty';

    public const STATUS_WEEKLY_OFF = 'weekly_off';

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_MISSING_PUNCH = 'missing_punch';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Present',
        self::STATUS_ABSENT => 'Absent',
        self::STATUS_HALF_DAY => 'Half Day',
        self::STATUS_WFH => 'Work From Home',
        self::STATUS_ON_DUTY => 'On Duty',
        self::STATUS_WEEKLY_OFF => 'Weekly Off',
        self::STATUS_HOLIDAY => 'Holiday',
        self::STATUS_LEAVE => 'Leave',
        self::STATUS_MISSING_PUNCH => 'Missing Punch',
    ];

    protected $fillable = [
        'employee_id', 'attendance_date', 'first_in', 'last_out', 'total_hours', 'effective_hours',
        'late_minutes', 'early_going_minutes', 'overtime_minutes', 'status', 'source', 'remarks', 'is_frozen',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'is_frozen' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    /**
     * False when there's only one real punch for the day (first_in and last_out end up
     * identical because AttendanceService/BiometricPunchService seed both from the same
     * single punch when the other is still null) — i.e. whenever displaying "in time" and
     * "out time" side by side would just show the same value twice. Callers should treat
     * that as a missing out-punch and not render last_out at all.
     */
    public function hasDistinctPunches(): bool
    {
        return $this->first_in && $this->last_out && $this->first_in !== $this->last_out;
    }

    /**
     * Display-safe out time: null when there's no real second punch (see hasDistinctPunches),
     * so callers can render "—" instead of duplicating the in time.
     */
    public function getDisplayLastOutAttribute(): ?string
    {
        return $this->hasDistinctPunches() ? $this->last_out : null;
    }
}
