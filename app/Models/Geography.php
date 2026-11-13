<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A node in the sales geography tree (e.g. West Zone > Mumbai Region > Thane Area > Thane HQ). */
class Geography extends Model
{
    use HasActiveScope, SoftDeletes;

    public const STATION_TYPES = [
        'hq' => 'HQ',
        'ex_hq' => 'Ex-HQ',
        'outstation' => 'Outstation',
    ];

    protected $fillable = [
        'company_id', 'geo_level_id', 'parent_id', 'name', 'code', 'state', 'city', 'station_type', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(GeoLevel::class, 'geo_level_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Geography::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Geography::class, 'parent_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /** "West > Mumbai > Thane" - walks up the tree (guarded against bad loops). */
    public function getPathAttribute(): string
    {
        $names = [];
        $node = $this;
        $seen = [];

        while ($node && ! in_array($node->id, $seen, true)) {
            $seen[] = $node->id;
            array_unshift($names, $node->name);
            $node = $node->parent;
        }

        return implode(' > ', $names);
    }

    /** @return array<int> ids of this node and everything below it */
    public function descendantAndSelfIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = static::query()->whereIn('parent_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    /** Would making $candidateParentId the parent of this node create a loop? */
    public function wouldCreateCycle(?int $candidateParentId): bool
    {
        if (! $this->exists || $candidateParentId === null) {
            return false;
        }

        return in_array($candidateParentId, $this->descendantAndSelfIds(), true);
    }
}
