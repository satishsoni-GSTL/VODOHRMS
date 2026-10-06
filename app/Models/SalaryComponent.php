<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryComponent extends Model
{
    use HasActiveScope;

    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_EMPLOYER_CONTRIBUTION = 'employer_contribution';

    public const CALC_FIXED = 'fixed';

    public const CALC_PERCENTAGE = 'percentage';

    public const CALC_FORMULA = 'formula';

    protected $fillable = [
        'name', 'code', 'type', 'calculation_type', 'percentage_of_component_id', 'default_percentage', 'default_amount', 'max_amount',
        'is_taxable', 'is_pf_applicable', 'is_esic_applicable', 'is_prorated',
        'is_ctc_component', 'is_gross_component', 'show_on_payslip', 'sequence', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_taxable' => 'boolean',
            'is_pf_applicable' => 'boolean',
            'is_esic_applicable' => 'boolean',
            'is_prorated' => 'boolean',
            'is_ctc_component' => 'boolean',
            'is_gross_component' => 'boolean',
            'show_on_payslip' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Monthly amount for an auto-computed Deduction / Employer Contribution: percentage of
     * Basic (or the fixed default), limited to max_amount when a cap is set (e.g. PF ₹3,000).
     */
    public function amountFromBasic(float $basic): float
    {
        $amount = match ($this->calculation_type) {
            self::CALC_PERCENTAGE => round($basic * (float) ($this->default_percentage ?? 0) / 100, 2),
            self::CALC_FIXED => (float) ($this->default_amount ?? 0),
            default => 0.0,
        };

        return $this->max_amount !== null ? min($amount, (float) $this->max_amount) : $amount;
    }

    public function percentageOfComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'percentage_of_component_id');
    }
}
