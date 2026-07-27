<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStatutoryDetail extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id', 'pan', 'aadhaar', 'uan', 'pf_number', 'esic_number',
        'professional_tax_applicable', 'tax_regime_default',
    ];

    protected function auditModule(): string
    {
        return 'employee';
    }

    protected function auditedEvents(): array
    {
        return ['created', 'updated'];
    }

    protected function casts(): array
    {
        return [
            'pan' => 'encrypted',
            'aadhaar' => 'encrypted',
            'professional_tax_applicable' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
