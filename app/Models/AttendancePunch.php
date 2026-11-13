<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    protected $fillable = [
        'attendance_id', 'punch_time', 'punch_type', 'source',
        'latitude', 'longitude', 'accuracy_m', 'location_id', 'distance_m', 'within_geofence',
    ];

    protected function casts(): array
    {
        return [
            'punch_time' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_m' => 'float',
            'within_geofence' => 'boolean',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function mapUrl(): ?string
    {
        return $this->hasCoordinates()
            ? "https://www.google.com/maps?q={$this->latitude},{$this->longitude}"
            : null;
    }
}
