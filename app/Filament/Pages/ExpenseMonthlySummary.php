<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Services\ExpenseMonthlySummaryService;
use Filament\Actions\Action as HeaderAction;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * HR/manager-facing, on-screen monthly expense summary: one row per employee, one
 * column per expense head (category), a per-employee total, and a totals footer.
 * "View" opens the same employee's day-wise breakdown for the month. An Excel copy
 * of the summary is available from the header ("Export to Excel"), and the day-wise
 * modal has its own export button.
 *
 * Team-scoping matches every other expense surface (App\Services\ExpenseMonthlySummaryService).
 */
class ExpenseMonthlySummary extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Monthly Expense Summary';

    protected static ?string $navigationGroup = 'Expenses';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.expense-monthly-summary';

    public string $month = '';

    /** @var array<string, mixed>|null memoised per Livewire render */
    private ?array $summaryCache = null;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updatedMonth(): void
    {
        $this->summaryCache = null;
        $this->resetTable();
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user->can('expense.view') || ($user->employee?->directReports()->exists() ?? false);
    }

    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('reports.download', ['type' => 'expense_monthly_summary', 'month' => $this->month]))
                ->openUrlInNewTab(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(): array
    {
        return $this->summaryCache ??= app(ExpenseMonthlySummaryService::class)->summary($this->month, auth()->user());
    }

    public function table(Table $table): Table
    {
        $summary = $this->summary();
        $rowsByEmployee = collect($summary['rows'])->keyBy('employee_id');

        $categoryColumns = collect($summary['categories'])
            ->map(fn (string $name, int $id) => Tables\Columns\TextColumn::make("category_{$id}")
                ->label($name)
                ->alignEnd()
                ->money('INR')
                ->state(fn (Employee $record) => $rowsByEmployee[$record->id]['by_category'][$id] ?? 0))
            ->values()
            ->all();

        return $table
            ->query(fn (): Builder => Employee::query()
                ->whereIn('id', $summary['employee_ids'] ?: [0])
                ->orderBy('employee_code'))
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')->label('Code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('full_name')->label('Employee')
                    ->searchable(['first_name', 'middle_name', 'last_name']),
                ...$categoryColumns,
                Tables\Columns\TextColumn::make('row_total')->label('Total')
                    ->alignEnd()
                    ->weight('bold')
                    ->money('INR')
                    ->state(fn (Employee $record) => $rowsByEmployee[$record->id]['total'] ?? 0),
            ])
            ->actions([
                Tables\Actions\Action::make('viewDayWise')
                    ->label('View')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->modalHeading(fn (Employee $record) => "{$record->full_name} — day-wise expenses ({$this->month})")
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (Employee $record) => view('filament.pages.expense-day-wise', [
                        'lines' => app(ExpenseMonthlySummaryService::class)->dayWise($this->month, $record->id, auth()->user()),
                        'downloadUrl' => route('reports.download', [
                            'type' => 'expense_daywise',
                            'month' => $this->month,
                            'employee' => $record->id,
                        ]),
                    ])),
            ])
            ->emptyStateHeading('No expenses in this month')
            ->emptyStateDescription('Pick another month, or check that expense claims have been raised.');
    }
}
