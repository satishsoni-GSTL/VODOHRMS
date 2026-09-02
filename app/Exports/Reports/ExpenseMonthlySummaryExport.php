<?php

namespace App\Exports\Reports;

use App\Models\User;
use App\Services\ExpenseMonthlySummaryService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Excel counterpart of the on-screen Monthly Expense Summary: employees as rows, one
 * column per expense head (every active category, so the layout is stable month to
 * month), a per-employee Total column, and a bold TOTAL footer row. Amounts are the
 * claimed (requested) amounts. Team-scoped via ExpenseMonthlySummaryService.
 */
class ExpenseMonthlySummaryExport implements FromArray, WithEvents, WithHeadings
{
    /** @var array<string, mixed>|null */
    private ?array $summary = null;

    public function __construct(private readonly string $month, private readonly User $user) {}

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return $this->summary ??= app(ExpenseMonthlySummaryService::class)->summary($this->month, $this->user);
    }

    public function headings(): array
    {
        return ['Employee Code', 'Name', ...array_values($this->summary()['categories']), 'Total'];
    }

    public function array(): array
    {
        $summary = $this->summary();
        $categoryIds = array_keys($summary['categories']);

        $rows = array_map(function (array $row) use ($categoryIds) {
            $amounts = array_map(fn ($id) => $row['by_category'][$id] ?? 0, $categoryIds);

            return [$row['employee_code'], $row['employee_name'], ...$amounts, $row['total']];
        }, $summary['rows']);

        if ($rows === []) {
            return [];
        }

        $totals = array_map(fn ($id) => $summary['totals'][$id] ?? 0, $categoryIds);
        $rows[] = ['', 'TOTAL', ...$totals, $summary['grand_total']];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->summary()['categories']) + 3);
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);

                if (! empty($this->summary()['rows'])) {
                    $sheet->getStyle("A{$lastRow}:{$lastColumn}{$lastRow}")->getFont()->setBold(true);
                }
            },
        ];
    }
}
