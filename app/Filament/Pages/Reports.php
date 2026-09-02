<?php

namespace App\Filament\Pages;

use App\Models\FinancialYear;
use Filament\Pages\Page;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reports';

    public string $month = '';

    public string $financialYear = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->financialYear = FinancialYear::where('is_active', true)->value('name') ?? '';
    }

    /**
     * @return array<int, string>
     */
    public function financialYearOptions(): array
    {
        return FinancialYear::query()->orderByDesc('start_date')->pluck('name', 'name')->all();
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user->can('employee.export')
            || $user->can('attendance.view')
            || $user->can('leave.view')
            || $user->can('expense.view')
            || $user->can('payroll.view')
            || $user->can('loan.view')
            || ($user->employee?->directReports()->exists() ?? false);
    }

    /**
     * @return array<int, array{type: string, label: string, description: string, needsMonth: bool}>
     */
    public function availableReports(): array
    {
        $user = auth()->user();
        $isManager = $user->employee?->directReports()->exists() ?? false;

        $reports = [
            ['type' => 'employee', 'label' => 'Employee Master', 'description' => 'Full employee list with department, designation, and status.', 'needsMonth' => false, 'visible' => $user->can('employee.export')],
            ['type' => 'my_attendance', 'label' => 'My Attendance', 'description' => 'Your own day-by-day attendance for the selected month, with in/out times.', 'needsMonth' => true, 'visible' => $user->employee !== null],
            ['type' => 'attendance', 'label' => 'Attendance', 'description' => 'Daily attendance status for the selected month.', 'needsMonth' => true, 'visible' => $user->can('attendance.view') || $isManager],
            ['type' => 'attendance_monthly_summary', 'label' => 'Employee Monthly Attendance Summary', 'description' => 'One row per employee, one column per day (in/out times, leave, holiday, weekly off) for the selected month.', 'needsMonth' => true, 'visible' => $user->can('attendance.view') || $isManager],
            ['type' => 'attendance_register', 'label' => 'Attendance Register', 'description' => 'Employee-wise rows, day-of-month columns, with first punch, last punch, and work hours shown in every cell.', 'needsMonth' => true, 'visible' => $user->can('attendance.view') || $isManager],
            ['type' => 'wfh', 'label' => 'Work From Home', 'description' => 'In/out time, hours completed, late mark, and completion status for every approved WFH day in the selected month.', 'needsMonth' => true, 'visible' => $user->can('attendance.view') || $isManager],
            ['type' => 'leave', 'label' => 'Leave', 'description' => 'Leave applications starting in the selected month.', 'needsMonth' => true, 'visible' => $user->can('leave.view') || $isManager],
            ['type' => 'expense', 'label' => 'Expense', 'description' => 'Expense claims raised in the selected month.', 'needsMonth' => true, 'visible' => $user->can('expense.view') || $isManager],
            ['type' => 'expense_monthly_summary', 'label' => 'Monthly Expense Summary (Employee-wise)', 'description' => 'One row per employee, one column per expense head, with per-employee and grand totals for the selected month.', 'needsMonth' => true, 'visible' => $user->can('expense.view') || $isManager],
            ['type' => 'payroll', 'label' => 'Payroll', 'description' => 'Payroll run summary for the selected month.', 'needsMonth' => true, 'visible' => $user->can('payroll.view')],
            ['type' => 'loan', 'label' => 'Loans & Advances', 'description' => 'All loans/advances and their outstanding balances.', 'needsMonth' => false, 'visible' => $user->can('loan.view') || $isManager],
            ['type' => 'payroll_expense_fy', 'label' => 'Payroll + Expense (Financial Year)', 'description' => 'Every employee\'s payroll register and expense claims for the selected financial year, in one workbook.', 'needsMonth' => false, 'needsFinancialYear' => true, 'visible' => $user->can('payroll.view')],
        ];

        return array_values(array_filter($reports, fn ($report) => $report['visible']));
    }
}
