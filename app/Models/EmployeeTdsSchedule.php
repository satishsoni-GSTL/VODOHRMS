<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One month of an employee's TDS plan for a financial year. Generated for all 12 months
 * (see TdsScheduleService::generate()) and then freely editable by HR; payroll deducts
 * exactly the saved amount for the run's month instead of recalculating TDS on the fly.
 */
class EmployeeTdsSchedule extends Model
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'tax';
    }

    protected $fillable = [
        'employee_id', 'financial_year_id', 'payroll_month', 'amount', 'is_manual', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_manual' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
