<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBankDetail extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id', 'account_holder_name', 'bank_name', 'account_number',
        'ifsc', 'branch_name', 'is_primary', 'effective_from',
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
            'account_number' => 'encrypted',
            'is_primary' => 'boolean',
            'effective_from' => 'date:Y-m-d',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        $number = (string) $this->account_number;

        return $number === '' ? '' : str_repeat('*', max(strlen($number) - 4, 0)).substr($number, -4);
    }
}
