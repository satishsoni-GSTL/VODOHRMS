<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A rung of the field-force ladder (MR, ABM, RBM, ZBM, NSM...). rank 1 = most senior. */
class HierarchyLevel extends Model
{
    use HasActiveScope;

    public const WORKFORCE_TYPES = [
        'field' => 'Field Force',
        'ho' => 'Head Office',
        'both' => 'Both',
    ];

    /** Standard Indian pharma sales ladder; geo_level is the GeoLevel code the role covers. */
    public const PHARMA_DEFAULTS = [
        ['short_name' => 'NSM', 'name' => 'National Sales Manager', 'rank' => 1, 'geo_level' => null],
        ['short_name' => 'ZBM', 'name' => 'Zonal Business Manager', 'rank' => 2, 'geo_level' => 'ZONE'],
        ['short_name' => 'RBM', 'name' => 'Regional Business Manager', 'rank' => 3, 'geo_level' => 'REGION'],
        ['short_name' => 'ABM', 'name' => 'Area Business Manager', 'rank' => 4, 'geo_level' => 'AREA'],
        ['short_name' => 'MR', 'name' => 'Medical Representative', 'rank' => 5, 'geo_level' => 'HQ'],
    ];

    protected $fillable = ['name', 'short_name', 'rank', 'workforce_type', 'geo_level_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rank' => 'integer'];
    }

    public function geoLevel(): BelongsTo
    {
        return $this->belongsTo(GeoLevel::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /** Inserts any missing standard geo + hierarchy levels; never changes existing rows. */
    public static function loadPharmaDefaults(): int
    {
        $created = 0;

        foreach (GeoLevel::PHARMA_DEFAULTS as $row) {
            if (! GeoLevel::where('code', $row['code'])->exists() && ! GeoLevel::where('depth', $row['depth'])->exists()) {
                GeoLevel::create($row + ['is_active' => true]);
                $created++;
            }
        }

        foreach (self::PHARMA_DEFAULTS as $row) {
            if (static::where('short_name', $row['short_name'])->exists()) {
                continue;
            }

            static::create([
                'short_name' => $row['short_name'],
                'name' => $row['name'],
                'rank' => $row['rank'],
                'workforce_type' => 'field',
                'geo_level_id' => $row['geo_level'] ? GeoLevel::where('code', $row['geo_level'])->value('id') : null,
                'is_active' => true,
            ]);
            $created++;
        }

        return $created;
    }
}
