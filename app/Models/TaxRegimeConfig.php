<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRegimeConfig extends Model
{
    protected $fillable = [
        'financial_year_id', 'regime', 'standard_deduction', 'rebate_limit_income', 'rebate_max_amount',
        'surcharge_rules', 'cess_percent', 'regime_change_allowed',
    ];

    protected function casts(): array
    {
        return [
            'surcharge_rules' => 'array',
            'regime_change_allowed' => 'boolean',
        ];
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }
}
