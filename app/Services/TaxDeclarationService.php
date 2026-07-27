<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeTaxDeclaration;
use App\Models\FinancialYear;
use App\Models\TaxSection;

class TaxDeclarationService
{
    public function declare(
        Employee $employee,
        FinancialYear $financialYear,
        TaxSection $section,
        float $amount,
        ?string $proofPath,
    ): EmployeeTaxDeclaration {
        return EmployeeTaxDeclaration::updateOrCreate(
            ['employee_id' => $employee->id, 'financial_year_id' => $financialYear->id, 'tax_section_id' => $section->id],
            [
                'declared_amount' => $amount,
                'proof_path' => $proofPath,
                'status' => $proofPath ? EmployeeTaxDeclaration::STATUS_PROOF_SUBMITTED : EmployeeTaxDeclaration::STATUS_DECLARED,
                'approved_amount' => null,
                'rejected_amount' => null,
                'eligible_amount' => null,
                'hr_remarks' => null,
            ]
        );
    }

    public function verify(EmployeeTaxDeclaration $declaration, float $approvedAmount, ?string $remarks = null): void
    {
        $maxLimit = $declaration->taxSection->max_limit;
        $eligible = $maxLimit !== null ? min($approvedAmount, (float) $maxLimit) : $approvedAmount;
        $rejected = max(0, (float) $declaration->declared_amount - $approvedAmount);

        $declaration->update([
            'approved_amount' => $approvedAmount,
            'rejected_amount' => $rejected,
            'eligible_amount' => $eligible,
            'hr_remarks' => $remarks,
            'status' => EmployeeTaxDeclaration::STATUS_VERIFIED,
        ]);
    }

    public function reject(EmployeeTaxDeclaration $declaration, string $remarks): void
    {
        $declaration->update([
            'approved_amount' => 0,
            'rejected_amount' => $declaration->declared_amount,
            'eligible_amount' => 0,
            'hr_remarks' => $remarks,
            'status' => EmployeeTaxDeclaration::STATUS_REJECTED,
        ]);
    }
}
