<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRegimeSlab extends Model
{
    public const REGIME_OLD = 'old';

    public const REGIME_NEW = 'new';

    protected $fillable = ['financial_year_id', 'regime', 'income_from', 'income_to', 'tax_percent', 'sequence'];

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }
}
