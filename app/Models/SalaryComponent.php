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
        'name', 'code', 'type', 'calculation_type', 'percentage_of_component_id', 'default_percentage', 'default_amount',
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

    public function percentageOfComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'percentage_of_component_id');
    }
}
