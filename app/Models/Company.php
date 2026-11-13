<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use App\Support\FieldForce;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasActiveScope, SoftDeletes;

    protected $fillable = ['name', 'code', 'address', 'gstin', 'pan', 'tan', 'logo_path', 'is_active', 'has_field_force'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'has_field_force' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FieldForce::flush());
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
