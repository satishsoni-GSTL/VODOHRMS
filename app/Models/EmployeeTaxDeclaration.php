<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaxDeclaration extends Model
{
    public const STATUS_DECLARED = 'declared';

    public const STATUS_PROOF_SUBMITTED = 'proof_submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id', 'financial_year_id', 'tax_section_id', 'declared_amount', 'proof_path',
        'approved_amount', 'rejected_amount', 'eligible_amount', 'hr_remarks', 'status',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function taxSection(): BelongsTo
    {
        return $this->belongsTo(TaxSection::class);
    }
}
