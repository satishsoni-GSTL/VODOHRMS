<?php

namespace App\Exports\Reports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeMasterExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return ['Employee Code', 'Name', 'Department', 'Designation', 'Company', 'Date of Joining', 'Status'];
    }

    public function collection()
    {
        return Employee::query()
            ->with(['department', 'designation', 'company'])
            ->orderBy('employee_code')
            ->get()
            ->map(fn (Employee $employee) => [
                $employee->employee_code,
                $employee->full_name,
                $employee->department?->name,
                $employee->designation?->name,
                $employee->company?->name,
                $employee->date_of_joining?->toDateString(),
                Employee::STATUSES[$employee->status] ?? $employee->status,
            ]);
    }
}
