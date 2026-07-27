<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class Phase4Seeder extends Seeder
{
    public function run(): void
    {
        $earnings = [
            ['name' => 'Basic', 'code' => 'BASIC', 'sequence' => 1],
            ['name' => 'House Rent Allowance', 'code' => 'HRA', 'sequence' => 2],
            ['name' => 'Conveyance Allowance', 'code' => 'CONVEYANCE', 'sequence' => 3],
            ['name' => 'Special Allowance', 'code' => 'SPECIAL_ALLOWANCE', 'sequence' => 4],
        ];

        foreach ($earnings as $earning) {
            SalaryComponent::firstOrCreate(['code' => $earning['code']], $earning + [
                'type' => SalaryComponent::TYPE_EARNING,
                'calculation_type' => SalaryComponent::CALC_FIXED,
                'is_taxable' => true,
                'is_pf_applicable' => $earning['code'] === 'BASIC',
                'is_prorated' => true,
                'is_ctc_component' => true,
                'is_gross_component' => true,
                'show_on_payslip' => true,
                'is_active' => true,
            ]);
        }

        $deductions = [
            ['name' => 'Provident Fund (Employee)', 'code' => 'PF_EMPLOYEE', 'calculation_type' => SalaryComponent::CALC_PERCENTAGE, 'default_percentage' => 12, 'sequence' => 10],
            ['name' => 'ESIC (Employee)', 'code' => 'ESIC_EMPLOYEE', 'calculation_type' => SalaryComponent::CALC_PERCENTAGE, 'default_percentage' => 0.75, 'sequence' => 11],
            ['name' => 'Professional Tax', 'code' => 'PROFESSIONAL_TAX', 'calculation_type' => SalaryComponent::CALC_FIXED, 'default_amount' => 200, 'sequence' => 12],
        ];

        foreach ($deductions as $deduction) {
            SalaryComponent::firstOrCreate(['code' => $deduction['code']], $deduction + [
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'is_taxable' => false,
                'is_prorated' => false,
                'is_ctc_component' => false,
                'is_gross_component' => false,
                'show_on_payslip' => true,
                'is_active' => true,
            ]);
        }

        $employerContributions = [
            ['name' => 'Provident Fund (Employer)', 'code' => 'PF_EMPLOYER', 'default_percentage' => 12, 'sequence' => 20],
            ['name' => 'ESIC (Employer)', 'code' => 'ESIC_EMPLOYER', 'default_percentage' => 3.25, 'sequence' => 21],
        ];

        foreach ($employerContributions as $contribution) {
            SalaryComponent::firstOrCreate(['code' => $contribution['code']], $contribution + [
                'type' => SalaryComponent::TYPE_EMPLOYER_CONTRIBUTION,
                'calculation_type' => SalaryComponent::CALC_PERCENTAGE,
                'is_taxable' => false,
                'is_prorated' => false,
                'is_ctc_component' => true,
                'is_gross_component' => false,
                'show_on_payslip' => false,
                'is_active' => true,
            ]);
        }
    }
}
