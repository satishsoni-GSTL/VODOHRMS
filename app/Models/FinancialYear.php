<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialYear extends Model
{
    use HasActiveScope;

    protected $fillable = ['name', 'start_date', 'end_date', 'assessment_year', 'is_active'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function slabs(): HasMany
    {
        return $this->hasMany(TaxRegimeSlab::class);
    }

    public function configs(): HasMany
    {
        return $this->hasMany(TaxRegimeConfig::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TaxSection::class);
    }

    public function slabsFor(string $regime)
    {
        return $this->slabs()->where('regime', $regime)->orderBy('sequence')->get();
    }

    public function configFor(string $regime): ?TaxRegimeConfig
    {
        return $this->configs()->where('regime', $regime)->first();
    }
}
