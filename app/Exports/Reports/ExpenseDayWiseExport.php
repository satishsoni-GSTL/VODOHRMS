<?php

namespace App\Exports\Reports;

use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\ExpenseMonthlySummaryService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Excel counterpart of the "View" drill-down on the Monthly Expense Summary page:
 * one row per expense claim line for a single employee in the month, oldest first.
 * Returns nothing when the employee is outside the requesting user's scope.
 */
class ExpenseDayWiseExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly string $month,
        private readonly int $employeeId,
        private readonly User $user,
    ) {}

    public function headings(): array
    {
        return ['Date', 'Head', 'Description', 'Vendor', 'Bill No.', 'Payment Mode', 'Requested Amount', 'Approved Amount', 'Claim Number', 'Status'];
    }

    public function collection()
    {
        return app(ExpenseMonthlySummaryService::class)
            ->dayWise($this->month, $this->employeeId, $this->user)
            ->map(fn (array $line) => [
                Carbon::parse($line['date'])->toDateString(),
                $line['category'],
                $line['description'],
                $line['vendor'],
                $line['bill_number'],
                $line['payment_mode'] ? ucfirst($line['payment_mode']) : null,
                $line['requested_amount'],
                $line['approved_amount'],
                $line['claim_number'],
                ExpenseClaim::STATUSES[$line['status']] ?? $line['status'],
            ]);
    }
}
