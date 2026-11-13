<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One tier of the sales geography (Zone, Region, Area, HQ, Territory). depth 1 = top. */
class GeoLevel extends Model
{
    use HasActiveScope;

    /** Standard Indian pharma geography, top to bottom. */
    public const PHARMA_DEFAULTS = [
        ['code' => 'ZONE', 'name' => 'Zone', 'depth' => 1],
        ['code' => 'REGION', 'name' => 'Region', 'depth' => 2],
        ['code' => 'AREA', 'name' => 'Area', 'depth' => 3],
        ['code' => 'HQ', 'name' => 'Headquarter', 'depth' => 4],
        ['code' => 'TERRITORY', 'name' => 'Territory', 'depth' => 5],
    ];

    protected $fillable = ['name', 'code', 'depth', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'depth' => 'integer'];
    }

    public function geographies(): HasMany
    {
        return $this->hasMany(Geography::class);
    }
}
