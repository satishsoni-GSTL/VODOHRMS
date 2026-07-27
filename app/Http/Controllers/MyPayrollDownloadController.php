<?php

namespace App\Http\Controllers;

use App\Exports\Reports\EmployeePayrollFinancialYearExport;
use App\Models\FinancialYear;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MyPayrollDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user->employee_id !== null, 403);

        $financialYear = $request->query('financial_year')
            ? FinancialYear::where('name', $request->query('financial_year'))->firstOrFail()
            : FinancialYear::where('is_active', true)->firstOrFail();

        $export = new EmployeePayrollFinancialYearExport($user->employee_id, $financialYear);

        return Excel::download($export, "my-payroll-sheet-{$financialYear->name}.xlsx");
    }
}
