<?php

namespace App\Filament\Pages;

use App\Models\FinancialYear;
use App\Models\Payslip;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MyPayslips extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'My Salary Slips';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.my-payslips';

    public string $financialYear = '';

    public function mount(): void
    {
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
        return (bool) auth()->user()?->employee_id;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Payslip::query()->with('payrollRunEmployee')->where('employee_id', auth()->user()->employee_id))
            ->columns([
                Tables\Columns\TextColumn::make('payroll_month')->label('Month')->sortable(),
                Tables\Columns\TextColumn::make('payrollRunEmployee.lop_days')
                    ->label('LOP Days')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->placeholder('0'),
                Tables\Columns\TextColumn::make('payrollRunEmployee.lop_amount')
                    ->label('LOP Amount')
                    ->money('INR')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('generated_at')->dateTime(),
            ])
            ->defaultSort('payroll_month', 'desc')
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Payslip $record) => route('payslips.download', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
