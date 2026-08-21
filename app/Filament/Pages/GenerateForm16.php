<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\FinancialYear;
use App\Models\Form16;
use App\Services\Form16Service;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class GenerateForm16 extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Generate Form 16';

    protected static ?string $navigationGroup = 'Income Tax';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.generate-form16';

    public ?int $financialYearId = null;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->can('tax.manage');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('tax.manage');
    }

    public function mount(): void
    {
        $this->financialYearId = FinancialYear::where('is_active', true)->value('id')
            ?? FinancialYear::query()->orderByDesc('start_date')->value('id');
    }

    /**
     * @return array<int, string>
     */
    public function financialYearOptions(): array
    {
        return FinancialYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all();
    }

    public function financialYear(): ?FinancialYear
    {
        return $this->financialYearId ? FinancialYear::find($this->financialYearId) : null;
    }

    public function updatedFinancialYearId(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Employee::query()->where('status', Employee::STATUS_ACTIVE)->orderBy('employee_code'))
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('full_name')->label('Employee')->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('regime')
                    ->label('Regime')
                    ->badge()
                    ->getStateUsing(fn (Employee $record) => $this->financialYear()
                        ? ucfirst($record->selectedRegimeFor($this->financialYear()) ?? 'old (default)')
                        : '—'),
                Tables\Columns\IconColumn::make('generated')
                    ->label('Form 16 Generated')
                    ->boolean()
                    ->getStateUsing(fn (Employee $record) => $this->financialYear() && Form16::query()
                        ->where('employee_id', $record->id)
                        ->where('financial_year_id', $this->financialYear()->id)
                        ->exists()),
            ])
            ->actions([
                Tables\Actions\Action::make('generate')
                    ->label(fn (Employee $record) => $this->form16For($record) ? 'Regenerate' : 'Generate')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn () => (bool) $this->financialYear())
                    ->requiresConfirmation()
                    ->action(function (Employee $record) {
                        app(Form16Service::class)->generate($record, $this->financialYear(), auth()->user());
                        Notification::make()->title('Form 16 generated for '.$record->full_name)->success()->send();
                    }),
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Employee $record) => (bool) $this->form16For($record))
                    ->url(fn (Employee $record) => route('form16.download', $this->form16For($record)))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('generateBulk')
                    ->label('Generate Form 16')
                    ->icon('heroicon-o-document-arrow-down')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $financialYear = $this->financialYear();
                        abort_unless($financialYear, 422);

                        foreach ($records as $record) {
                            app(Form16Service::class)->generate($record, $financialYear, auth()->user());
                        }

                        Notification::make()->title('Form 16 generated for '.$records->count().' employee(s)')->success()->send();
                    }),
            ]);
    }

    private function form16For(Employee $record): ?Form16
    {
        if (! $this->financialYear()) {
            return null;
        }

        return Form16::query()
            ->where('employee_id', $record->id)
            ->where('financial_year_id', $this->financialYear()->id)
            ->first();
    }
}
