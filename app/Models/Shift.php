<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasActiveScope;

    protected $fillable = [
        'name', 'type', 'start_time', 'end_time', 'grace_minutes', 'break_minutes',
        'min_full_day_hours', 'min_half_day_hours', 'late_mark_after_minutes',
        'early_going_before_minutes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'min_full_day_hours' => 'decimal:2',
            'min_half_day_hours' => 'decimal:2',
        ];
    }

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }
}
