<?php

namespace Database\Seeders;

use App\Models\FinancialYear;
use App\Models\TaxRegimeConfig;
use App\Models\TaxRegimeSlab;
use App\Models\TaxSection;
use Illuminate\Database\Seeder;

class Phase5Seeder extends Seeder
{
    public function run(): void
    {
        $fy = FinancialYear::firstOrCreate(
            ['name' => '2026-27'],
            [
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'assessment_year' => '2027-28',
                'is_active' => true,
            ]
        );

        if (! $fy->slabs()->exists()) {
            $oldSlabs = [
                [0, 250000, 0],
                [250000, 500000, 5],
                [500000, 1000000, 20],
                [1000000, null, 30],
            ];
            $newSlabs = [
                [0, 300000, 0],
                [300000, 700000, 5],
                [700000, 1000000, 10],
                [1000000, 1200000, 15],
                [1200000, 1500000, 20],
                [1500000, null, 30],
            ];

            foreach ([TaxRegimeSlab::REGIME_OLD => $oldSlabs, TaxRegimeSlab::REGIME_NEW => $newSlabs] as $regime => $slabs) {
                foreach ($slabs as $sequence => [$from, $to, $percent]) {
                    TaxRegimeSlab::create([
                        'financial_year_id' => $fy->id,
                        'regime' => $regime,
                        'income_from' => $from,
                        'income_to' => $to,
                        'tax_percent' => $percent,
                        'sequence' => $sequence + 1,
                    ]);
                }
            }
        }

        TaxRegimeConfig::firstOrCreate(
            ['financial_year_id' => $fy->id, 'regime' => TaxRegimeSlab::REGIME_OLD],
            [
                'standard_deduction' => 50000,
                'rebate_limit_income' => 500000,
                'rebate_max_amount' => 12500,
                'cess_percent' => 4,
                'regime_change_allowed' => true,
            ]
        );

        TaxRegimeConfig::firstOrCreate(
            ['financial_year_id' => $fy->id, 'regime' => TaxRegimeSlab::REGIME_NEW],
            [
                'standard_deduction' => 75000,
                'rebate_limit_income' => 700000,
                'rebate_max_amount' => 25000,
                'cess_percent' => 4,
                'regime_change_allowed' => true,
            ]
        );

        $sections = [
            ['code' => '80C', 'name' => 'Section 80C (PF, ELSS, Life Insurance, etc.)', 'max_limit' => 150000],
            ['code' => '80D', 'name' => 'Section 80D (Medical Insurance Premium)', 'max_limit' => 25000],
            ['code' => 'HRA', 'name' => 'House Rent Allowance Exemption', 'max_limit' => null],
            ['code' => 'HOME_LOAN_INTEREST', 'name' => 'Home Loan Interest (Section 24)', 'max_limit' => 200000],
            ['code' => 'NPS', 'name' => 'National Pension Scheme (Section 80CCD(1B))', 'max_limit' => 50000],
        ];

        foreach ($sections as $section) {
            TaxSection::firstOrCreate(
                ['financial_year_id' => $fy->id, 'code' => $section['code']],
                $section + ['financial_year_id' => $fy->id, 'is_active' => true]
            );
        }
    }
}
