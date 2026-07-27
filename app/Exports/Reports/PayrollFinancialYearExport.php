<?php

namespace App\Exports\Reports;

use App\Models\FinancialYear;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PayrollFinancialYearExport implements WithMultipleSheets
{
    public function __construct(private readonly FinancialYear $financialYear) {}

    public function sheets(): array
    {
        return [
            new PayrollFinancialYearPayrollSheet($this->financialYear),
            new PayrollFinancialYearExpenseSheet($this->financialYear),
        ];
    }
}
