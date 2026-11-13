<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasActiveScope, SoftDeletes;

    protected $fillable = [
        'branch_id', 'name', 'city', 'state', 'country', 'pincode', 'is_active',
        'latitude', 'longitude', 'geofence_radius_m',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'geofence_radius_m' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hasGeofence(): bool
    {
        return $this->latitude !== null && $this->longitude !== null && $this->geofence_radius_m > 0;
    }

    /** Straight-line (haversine) distance in metres from this location's centre. */
    public function distanceTo(float $latitude, float $longitude): int
    {
        return self::distanceBetween($this->latitude, $this->longitude, $latitude, $longitude);
    }

    public static function distanceBetween(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
