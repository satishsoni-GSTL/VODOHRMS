<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryStructureLine extends Model
{
    protected $fillable = ['structure_id', 'salary_component_id', 'monthly_amount', 'annual_amount'];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryStructure::class, 'structure_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
