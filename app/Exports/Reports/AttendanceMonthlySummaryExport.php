<?php

namespace App\Exports\Reports;

use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceMonthlySummaryService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AttendanceMonthlySummaryExport implements FromArray, WithEvents, WithHeadings
{
    private Carbon $monthStart;

    private Carbon $monthEnd;

    /**
     * Fill/font colors per status code, keyed the same as AttendanceMonthlySummaryService::CODE_*.
     * Mirrors the color scheme used in the on-screen Monthly Attendance View.
     */
    private const CELL_COLORS = [
        AttendanceMonthlySummaryService::CODE_PRESENT => ['fill' => 'C6EFCE', 'font' => '006100'],
        AttendanceMonthlySummaryService::CODE_HALF_DAY => ['fill' => 'FFEB9C', 'font' => '9C6500'],
        AttendanceMonthlySummaryService::CODE_WFH => ['fill' => 'DDEBF7', 'font' => '1F4E78'],
        AttendanceMonthlySummaryService::CODE_ON_DUTY => ['fill' => 'DDEBF7', 'font' => '1F4E78'],
        AttendanceMonthlySummaryService::CODE_LEAVE => ['fill' => 'E4DFEC', 'font' => '60497A'],
        AttendanceMonthlySummaryService::CODE_HOLIDAY => ['fill' => 'D9D9D9', 'font' => '404040'],
        AttendanceMonthlySummaryService::CODE_WEEKLY_OFF => ['fill' => 'F2F2F2', 'font' => '808080'],
        AttendanceMonthlySummaryService::CODE_ABSENT => ['fill' => 'FFC7CE', 'font' => '9C0006'],
        AttendanceMonthlySummaryService::CODE_MISSING_PUNCH => ['fill' => 'FCE4D6', 'font' => '833C00'],
    ];

    public function __construct(private readonly string $month, private readonly User $user) {}

    public function headings(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        $dayHeadings = [];
        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $dayHeadings[] = (string) $day;
        }

        return [
            'Employee Code', 'Name', ...$dayHeadings,
            'Present', 'Half Day', 'Leave', 'Holiday', 'Weekly Off', 'Absent',
        ];
    }

    public function array(): array
    {
        $this->monthStart = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $this->monthEnd = (clone $this->monthStart)->endOfMonth();

        $summaryService = app(AttendanceMonthlySummaryService::class);

        return $this->employees()->map(
            fn (Employee $employee) => $this->rowForEmployee($employee, $summaryService)
        )->all();
    }

    private function employees()
    {
        $query = Employee::query()->orderBy('employee_code');

        if ($this->user->can('attendance.view')) {
            return $query->get();
        }

        $employee = $this->user->employee;
        $visibleIds = $employee ? [$employee->id, ...$employee->allSubordinateIds()] : [];

        return $query->whereIn('id', $visibleIds)->get();
    }

    private function rowForEmployee(Employee $employee, AttendanceMonthlySummaryService $summaryService): array
    {
        $days = $summaryService->buildForEmployee($employee, $this->monthStart, $this->monthEnd);

        $counts = array_count_values(array_column($days, 'code'));

        return [
            $employee->employee_code,
            $employee->full_name,
            ...array_map(fn (array $cell) => $cell['label'], array_values($days)),
            $counts[AttendanceMonthlySummaryService::CODE_PRESENT] ?? 0,
            $counts[AttendanceMonthlySummaryService::CODE_HALF_DAY] ?? 0,
            $counts[AttendanceMonthlySummaryService::CODE_LEAVE] ?? 0,
            $counts[AttendanceMonthlySummaryService::CODE_HOLIDAY] ?? 0,
            $counts[AttendanceMonthlySummaryService::CODE_WEEKLY_OFF] ?? 0,
            $counts[AttendanceMonthlySummaryService::CODE_ABSENT] ?? 0,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $daysInMonth = Carbon::createFromFormat('Y-m', $this->month)->daysInMonth;

                $firstDayColumn = 3; // A = Employee Code, B = Name, C = day 1
                $lastDayColumn = 2 + $daysInMonth;
                $lastRow = $sheet->getHighestRow();

                for ($row = 2; $row <= $lastRow; $row++) {
                    for ($col = $firstDayColumn; $col <= $lastDayColumn; $col++) {
                        $coordinate = Coordinate::stringFromColumnIndex($col).$row;
                        $value = (string) $sheet->getCell($coordinate)->getValue();

                        if ($value === '') {
                            continue;
                        }

                        $colors = self::CELL_COLORS[strtok($value, ' ')] ?? null;

                        if (! $colors) {
                            continue;
                        }

                        $style = $sheet->getStyle($coordinate);
                        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colors['fill']);
                        $style->getFont()->getColor()->setRGB($colors['font']);
                    }
                }
            },
        ];
    }
}
