<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaxProjection extends Model
{
    protected $fillable = [
        'employee_id', 'financial_year_id', 'regime', 'projected_annual_income', 'total_exemptions',
        'taxable_income', 'tax_before_rebate', 'rebate', 'surcharge', 'cess', 'final_tax',
        'tds_deducted_till_date', 'remaining_tax', 'projected_monthly_tds', 'calculated_at',
    ];

    protected function casts(): array
    {
        return ['calculated_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }
}
