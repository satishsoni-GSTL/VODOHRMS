<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaxRegime extends Model
{
    protected $fillable = ['employee_id', 'financial_year_id', 'selected_regime', 'selection_date', 'lock_date', 'changed_by'];

    protected function casts(): array
    {
        return [
            'selection_date' => 'date:Y-m-d',
            'lock_date' => 'date:Y-m-d',
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

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
