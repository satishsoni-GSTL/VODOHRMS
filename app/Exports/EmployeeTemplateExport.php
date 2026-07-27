<?php

namespace App\Exports;

use App\Services\EmployeeImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeTemplateExport implements FromArray, WithStyles
{
    public function array(): array
    {
        return [EmployeeImportService::TEMPLATE_COLUMNS];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
