<?php

namespace App\Exports\Reports;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceMonthlySummaryService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel counterpart of the on-screen Attendance Register: employees as rows, one column
 * per calendar day, each cell holding first punch / last punch / work hours stacked on
 * three lines (wrapped text). No-punch days (leave/holiday/weekly-off/absent) fall back to
 * the short status code, same as AttendanceMonthlySummaryExport.
 */
class AttendanceRegisterExport implements FromArray, WithEvents, WithHeadings
{
    private Carbon $monthStart;

    /**
     * [employeeCode => [dayNumber => 'complete'|'incomplete']] for every punch cell, built
     * while array() runs so registerEvents()'s AfterSheet coloring pass (which only sees raw
     * cell text) knows which cells to highlight without having to re-derive it from the text.
     *
     * @var array<string, array<int, string>>
     */
    private array $completion = [];

    public function __construct(private readonly string $month, private readonly User $user) {}

    public function headings(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        $dayHeadings = [];
        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $dayHeadings[] = (string) $day;
        }

        return ['Employee Code', 'Name', ...$dayHeadings];
    }

    public function array(): array
    {
        $this->monthStart = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $monthEnd = (clone $this->monthStart)->endOfMonth();

        $summaryService = app(AttendanceMonthlySummaryService::class);
        $attendanceService = app(AttendanceService::class);

        return $this->employees()->map(function (Employee $employee) use ($summaryService, $attendanceService, $monthEnd) {
            $days = $summaryService->buildForEmployee($employee, $this->monthStart, $monthEnd);
            $minFullDayHours = $attendanceService->minFullDayHoursFor($employee, $this->monthStart);

            $dayNumber = 0;
            $cells = array_map(function (array $cell) use (&$dayNumber, $employee, $minFullDayHours) {
                $dayNumber++;

                if ($cell['hours'] !== null) {
                    $this->completion[$employee->employee_code][$dayNumber] =
                        $cell['hours'] >= $minFullDayHours ? 'complete' : 'incomplete';
                }

                return $this->cellText($cell);
            }, array_values($days));

            return [$employee->employee_code, $employee->full_name, ...$cells];
        })->all();
    }

    private function cellText(array $cell): string
    {
        if ($cell['first_in']) {
            $lastOut = $cell['last_out'] ?? '—';
            $hours = Attendance::formatHours($cell['hours']) ?? '—';

            return "{$cell['first_in']}\n{$lastOut}\n{$hours}";
        }

        return $cell['label'];
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $daysInMonth = Carbon::createFromFormat('Y-m', $this->month)->daysInMonth;

                $firstDayColumn = 3; // A = Employee Code, B = Name, C = day 1
                $lastDayColumn = 2 + $daysInMonth;
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex($lastDayColumn).$lastRow)
                    ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

                for ($row = 2; $row <= $lastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(40);

                    $employeeCode = (string) $sheet->getCell("A{$row}")->getValue();

                    for ($col = $firstDayColumn; $col <= $lastDayColumn; $col++) {
                        $coordinate = Coordinate::stringFromColumnIndex($col).$row;
                        $value = (string) $sheet->getCell($coordinate)->getValue();

                        if ($value === '') {
                            continue;
                        }

                        $day = $col - $firstDayColumn + 1;
                        $completionState = $this->completion[$employeeCode][$day] ?? null;

                        if ($completionState !== null) {
                            // Punch cell: highlight the whole cell by hours complete/incomplete,
                            // reusing the same Present/Absent palette the status-code cells use.
                            $code = $completionState === 'complete'
                                ? AttendanceMonthlySummaryService::CODE_PRESENT
                                : AttendanceMonthlySummaryService::CODE_ABSENT;
                        } else {
                            $code = strtok($value, "\n ");
                        }

                        $colors = AttendanceMonthlySummaryService::CODE_COLORS[$code] ?? null;

                        if (! $colors) {
                            continue;
                        }

                        $style = $sheet->getStyle($coordinate);
                        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(ltrim($colors['bg'], '#'));
                        $style->getFont()->getColor()->setRGB(ltrim($colors['text'], '#'));
                    }
                }
            },
        ];
    }
}
