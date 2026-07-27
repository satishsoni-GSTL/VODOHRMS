<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSalaryStructure extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id', 'effective_from', 'effective_to', 'annual_ctc', 'monthly_gross',
        'previous_ctc', 'revised_ctc', 'increment_percent', 'approved_by', 'remarks', 'is_active',
    ];

    protected function auditModule(): string
    {
        return 'payroll';
    }

    protected function auditedEvents(): array
    {
        return ['created', 'updated'];
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EmployeeSalaryStructureLine::class, 'structure_id');
    }
}
