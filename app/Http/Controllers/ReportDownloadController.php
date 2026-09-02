<?php

namespace App\Http\Controllers;

use App\Exports\Reports\AttendanceMonthlySummaryExport;
use App\Exports\Reports\AttendanceRegisterExport;
use App\Exports\Reports\AttendanceReportExport;
use App\Exports\Reports\EmployeeMasterExport;
use App\Exports\Reports\ExpenseDayWiseExport;
use App\Exports\Reports\ExpenseMonthlySummaryExport;
use App\Exports\Reports\ExpenseReportExport;
use App\Exports\Reports\LeaveReportExport;
use App\Exports\Reports\LoanReportExport;
use App\Exports\Reports\MyAttendanceExport;
use App\Exports\Reports\PayrollFinancialYearExport;
use App\Exports\Reports\PayrollReportExport;
use App\Exports\Reports\WfhReportExport;
use App\Models\FinancialYear;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportDownloadController extends Controller
{
    private const PERMISSIONS = [
        'employee' => 'employee.export',
        'attendance' => 'attendance.view',
        'attendance_monthly_summary' => 'attendance.view',
        'attendance_register' => 'attendance.view',
        'wfh' => 'attendance.view',
        'leave' => 'leave.view',
        'expense' => 'expense.view',
        'expense_monthly_summary' => 'expense.view',
        'expense_daywise' => 'expense.view',
        'payroll' => 'payroll.view',
        'loan' => 'loan.view',
        'payroll_expense_fy' => 'payroll.view',
    ];

    private const TEAM_SCOPED_TYPES = ['attendance', 'attendance_monthly_summary', 'attendance_register', 'wfh', 'leave', 'expense', 'expense_monthly_summary', 'expense_daywise', 'loan'];

    public function __invoke(Request $request, string $type): BinaryFileResponse
    {
        $user = $request->user();

        if ($type === 'my_attendance') {
            // Always the caller's own employee record — not team-scoped, so no
            // attendance.view/manager check, just "do they have an employee record".
            abort_unless($user->employee !== null, 403);
        } else {
            $permission = self::PERMISSIONS[$type] ?? null;
            abort_unless($permission !== null, 404);

            $isManager = $user->employee?->directReports()->exists() ?? false;
            abort_unless($user->can($permission) || ($isManager && in_array($type, self::TEAM_SCOPED_TYPES, true)), 403);
        }

        $month = $request->query('month', now()->format('Y-m'));

        [$export, $filename] = match ($type) {
            'employee' => [new EmployeeMasterExport, 'employee-master-report.xlsx'],
            'my_attendance' => [new MyAttendanceExport($month, $user), "my-attendance-{$month}.xlsx"],
            'attendance' => [new AttendanceReportExport($month, $user), "attendance-report-{$month}.xlsx"],
            'attendance_monthly_summary' => [new AttendanceMonthlySummaryExport($month, $user), "attendance-monthly-summary-{$month}.xlsx"],
            'attendance_register' => [new AttendanceRegisterExport($month, $user), "attendance-register-{$month}.xlsx"],
            'wfh' => [new WfhReportExport($month, $user), "wfh-report-{$month}.xlsx"],
            'leave' => [new LeaveReportExport($month, $user), "leave-report-{$month}.xlsx"],
            'expense' => [new ExpenseReportExport($month, $user), "expense-report-{$month}.xlsx"],
            'expense_monthly_summary' => [new ExpenseMonthlySummaryExport($month, $user), "expense-monthly-summary-{$month}.xlsx"],
            'expense_daywise' => [new ExpenseDayWiseExport($month, (int) $request->query('employee'), $user), "expense-daywise-{$month}.xlsx"],
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
