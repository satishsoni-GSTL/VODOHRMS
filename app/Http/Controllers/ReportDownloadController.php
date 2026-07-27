<?php

namespace App\Http\Controllers;

use App\Exports\Reports\AttendanceReportExport;
use App\Exports\Reports\EmployeeMasterExport;
use App\Exports\Reports\ExpenseReportExport;
use App\Exports\Reports\LeaveReportExport;
use App\Exports\Reports\LoanReportExport;
use App\Exports\Reports\PayrollFinancialYearExport;
use App\Exports\Reports\PayrollReportExport;
use App\Models\FinancialYear;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportDownloadController extends Controller
{
    private const PERMISSIONS = [
        'employee' => 'employee.export',
        'attendance' => 'attendance.view',
        'leave' => 'leave.view',
        'expense' => 'expense.view',
        'payroll' => 'payroll.view',
        'loan' => 'loan.view',
        'payroll_expense_fy' => 'payroll.view',
    ];

    private const TEAM_SCOPED_TYPES = ['attendance', 'leave', 'expense', 'loan'];

    public function __invoke(Request $request, string $type): BinaryFileResponse
    {
        $user = $request->user();
        $permission = self::PERMISSIONS[$type] ?? null;
        abort_unless($permission !== null, 404);

        $isManager = $user->employee?->directReports()->exists() ?? false;
        abort_unless($user->can($permission) || ($isManager && in_array($type, self::TEAM_SCOPED_TYPES, true)), 403);

        $month = $request->query('month', now()->format('Y-m'));

        [$export, $filename] = match ($type) {
            'employee' => [new EmployeeMasterExport, 'employee-master-report.xlsx'],
            'attendance' => [new AttendanceReportExport($month, $user), "attendance-report-{$month}.xlsx"],
            'leave' => [new LeaveReportExport($month, $user), "leave-report-{$month}.xlsx"],
            'expense' => [new ExpenseReportExport($month, $user), "expense-report-{$month}.xlsx"],
            'payroll' => [new PayrollReportExport($month), "payroll-report-{$month}.xlsx"],
            'loan' => [new LoanReportExport($user), 'loan-report.xlsx'],
            'payroll_expense_fy' => (function () use ($request) {
                $fy = FinancialYear::where('name', $request->query('financial_year'))->firstOrFail();

                return [new PayrollFinancialYearExport($fy), "payroll-expense-{$fy->name}.xlsx"];
            })(),
        };

        return Excel::download($export, $filename);
    }
}
