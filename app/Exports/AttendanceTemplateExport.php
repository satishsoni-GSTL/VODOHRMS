<?php

namespace App\Exports;

use App\Services\AttendanceImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceTemplateExport implements FromArray, WithStyles
{
    public function array(): array
    {
        return [AttendanceImportService::TEMPLATE_COLUMNS];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
