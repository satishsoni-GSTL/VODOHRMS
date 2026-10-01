<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Holiday extends Model
{
    public const TYPE_OPTIONAL = 'optional';

    public const TYPES = [
        'national' => 'National Holiday',
        'state' => 'State Holiday',
        'company' => 'Company Holiday',
        self::TYPE_OPTIONAL => 'Optional Holiday',
    ];

    protected $fillable = ['name', 'date', 'type', 'company_id', 'branch_id', 'location_id', 'state'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(OptionalHolidayClaim::class);
    }

    public function isOptional(): bool
    {
        return $this->type === self::TYPE_OPTIONAL;
    }

    /**
     * Holidays this employee actually gets off: everything for their company (or company-wide),
     * except optional holidays, which are a normal working day unless the employee has claimed them.
     */
    public function scopeObservedBy(Builder $query, Employee $employee): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->where(fn ($q) => $q->where('type', '!=', self::TYPE_OPTIONAL)
                ->orWhereHas('claims', fn ($c) => $c
                    ->where('employee_id', $employee->id)
                    ->where('status', OptionalHolidayClaim::STATUS_CLAIMED)));
    }
}
