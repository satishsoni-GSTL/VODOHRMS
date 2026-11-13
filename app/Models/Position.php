<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A seat in the field-force structure (e.g. "MR - Thane HQ - Cardio"). Positions exist
 * independently of people, so a territory stays on the organogram (flagged vacant) when
 * its MR leaves, and the reporting line is defined between seats, not individuals.
 */
class Position extends Model
{
    use HasActiveScope, SoftDeletes;

    protected $fillable = [
        'company_id', 'division_id', 'hierarchy_level_id', 'geography_id', 'parent_position_id',
        'code', 'title', 'employee_id', 'vacant_since', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'vacant_since' => 'date:Y-m-d'];
    }

    protected static function booted(): void
    {
        // A new seat starts vacant from the day it is created.
        static::creating(function (Position $position): void {
            if (! $position->employee_id && ! $position->vacant_since) {
                $position->vacant_since = now()->toDateString();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function hierarchyLevel(): BelongsTo
    {
        return $this->belongsTo(HierarchyLevel::class);
    }

    public function geography(): BelongsTo
    {
        return $this->belongsTo(Geography::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'parent_position_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Position::class, 'parent_position_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    public function isVacant(): bool
    {
        return $this->employee_id === null;
    }

    /** @return array<int> ids of this position and every position below it */
    public function descendantAndSelfIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = static::query()->whereIn('parent_position_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    public function wouldCreateCycle(?int $candidateParentId): bool
    {
        if (! $this->exists || $candidateParentId === null) {
            return false;
        }

        return in_array($candidateParentId, $this->descendantAndSelfIds(), true);
    }

    /** Nearest filled position above this one (skipping vacant seats), or null. */
    public function nearestFilledAncestor(): ?Position
    {
        $node = $this->parent;
        $seen = [$this->id];

        while ($node && ! in_array($node->id, $seen, true)) {
            if ($node->employee_id) {
                return $node;
            }
            $seen[] = $node->id;
            $node = $node->parent;
        }

        return null;
    }
}
