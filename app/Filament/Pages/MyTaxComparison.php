<?php

namespace App\Filament\Pages;

use App\Models\FinancialYear;
use App\Services\IncomeTaxCalculationService;
use Filament\Pages\Page;

class MyTaxComparison extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'My Tax Comparison';

    protected static ?string $navigationGroup = 'Income Tax';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.my-tax-comparison';

    public ?array $comparison = null;

    public ?FinancialYear $financialYear = null;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->employee_id;
    }

    public function mount(): void
    {
        $this->financialYear = FinancialYear::where('is_active', true)->orderByDesc('start_date')->first();

        if (! $this->financialYear) {
            return;
        }

        $employee = auth()->user()->employee;
        $payrollMonth = now()->format('Y-m');

        $result = app(IncomeTaxCalculationService::class)->compareRegimes($employee, $this->financialYear, $payrollMonth);

        $this->comparison = [
            'old' => $result['old'],
            'new' => $result['new'],
            'recommended' => $result['recommended'],
        ];
    }
}
