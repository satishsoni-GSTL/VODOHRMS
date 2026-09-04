<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeSalaryStructureResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeSalaryStructure;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Services\SalaryStructureService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class EmployeeSalaryStructureResource extends Resource
{
    protected static ?string $model = EmployeeSalaryStructure::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-rupee';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Salary Structures';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'employee_code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->employee_code} - {$record->full_name}")
                    ->searchable(['employee_code', 'first_name', 'last_name'])
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('effective_from')->default(now())->required(),
                Forms\Components\TextInput::make('annual_ctc')->numeric()->required()->prefix('₹')->live(onBlur: true),
                Forms\Components\Repeater::make('lines')
                    ->label('Earning Components (monthly amounts)')
                    ->schema([
                        Forms\Components\Select::make('salary_component_id')
                            ->label('Component')
                            ->options(fn () => SalaryComponent::query()->active()->where('type', SalaryComponent::TYPE_EARNING)->orderBy('sequence')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('monthly_amount')->numeric()->required()->prefix('₹'),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Statutory deductions (PF, ESIC, Professional Tax) and employer contributions are computed automatically from the Basic component unless you enter them below.'),
                Forms\Components\Repeater::make('deduction_lines')
                    ->label('Deduction Components (monthly amounts)')
                    ->schema([
                        Forms\Components\Select::make('salary_component_id')
                            ->label('Component')
                            ->options(fn () => SalaryComponent::query()->active()->where('type', SalaryComponent::TYPE_DEDUCTION)->orderBy('sequence')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Forms\Components\TextInput::make('monthly_amount')->numeric()->required()->prefix('₹'),
                    ])
                    ->columns(2)
                    ->minItems(0)
                    ->columnSpanFull()
                    ->helperText('Optional. Add a fixed deduction (e.g. a recovery), or set a statutory one by hand to override the auto-computed value.'),
                Forms\Components\Textarea::make('remarks')->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('effective_from')->date()->sortable(),
                Tables\Columns\TextColumn::make('effective_to')->date()->placeholder('Current'),
                Tables\Columns\TextColumn::make('annual_ctc')->money('INR'),
                Tables\Columns\TextColumn::make('monthly_gross')->money('INR'),
                Tables\Columns\TextColumn::make('increment_percent')->suffix('%')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Current Structure'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('reviseSalary')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn (EmployeeSalaryStructure $record) => $record->is_active
                        && (auth()->user()?->can('payroll.manage') ?? false))
                    ->form([
                        Forms\Components\Placeholder::make('employee_display')
                            ->label('Employee')
                            ->content(fn (EmployeeSalaryStructure $record) => "{$record->employee->employee_code} - {$record->employee->full_name}"),
                        Forms\Components\Toggle::make('backdate')
                            ->label('Apply from an earlier (past) date')
                            ->helperText('Off: the revision applies from today onward and never touches a month already run. On: pick any date after the current structure started — recalculate/raise arrears for any affected month.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\DatePicker::make('effective_from')
                            ->default(now())
                            ->required()
                            ->live(onBlur: true)
                            ->minDate(fn (Forms\Get $get, EmployeeSalaryStructure $record) => $get('backdate')
                                ? $record->effective_from->copy()->addDay()
                                : now()->startOfDay())
                            ->helperText(fn (Forms\Get $get, EmployeeSalaryStructure $record) => $get('backdate')
                                ? 'Must be after '.$record->effective_from->toDateString().' (current structure start).'
                                : null),
                        Forms\Components\Placeholder::make('backdate_warning')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get, EmployeeSalaryStructure $record) => filled($get('effective_from'))
                                && static::finalizedMonthWarning($get('effective_from'), $record->employee) !== null)
                            ->content(fn (Forms\Get $get, EmployeeSalaryStructure $record) => static::finalizedMonthWarning($get('effective_from'), $record->employee)),
                        Forms\Components\TextInput::make('annual_ctc')
                            ->numeric()
                            ->required()
                            ->prefix('₹')
                            ->default(fn (EmployeeSalaryStructure $record) => $record->annual_ctc),
                        Forms\Components\Repeater::make('lines')
                            ->label('Earning Components (monthly amounts)')
                            ->schema([
                                Forms\Components\Select::make('salary_component_id')
                                    ->label('Component')
                                    ->options(fn () => SalaryComponent::query()->active()->where('type', SalaryComponent::TYPE_EARNING)->orderBy('sequence')->pluck('name', 'id'))
                                    ->required()
                                    ->searchable(),
                                Forms\Components\TextInput::make('monthly_amount')->numeric()->required()->prefix('₹'),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->required()
                            ->columnSpanFull()
                            ->default(fn (EmployeeSalaryStructure $record) => $record->lines()
                                ->whereHas('component', fn ($q) => $q->where('type', SalaryComponent::TYPE_EARNING))
                                ->get()
                                ->map(fn ($line) => [
                                    'salary_component_id' => $line->salary_component_id,
                                    'monthly_amount' => $line->monthly_amount,
                                ])
                                ->all()),
                        Forms\Components\Repeater::make('deduction_lines')
                            ->label('Deduction Components (monthly amounts)')
                            ->schema([
                                Forms\Components\Select::make('salary_component_id')
                                    ->label('Component')
                                    ->options(fn () => SalaryComponent::query()->active()->where('type', SalaryComponent::TYPE_DEDUCTION)->orderBy('sequence')->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Forms\Components\TextInput::make('monthly_amount')->numeric()->required()->prefix('₹'),
                            ])
                            ->columns(2)
                            ->minItems(0)
                            ->columnSpanFull()
                            ->helperText('Editing a value here fixes it for this version instead of auto-computing it. Remove a row to hand it back to auto-compute.')
                            ->default(fn (EmployeeSalaryStructure $record) => $record->lines()
                                ->whereHas('component', fn ($q) => $q->where('type', SalaryComponent::TYPE_DEDUCTION))
                                ->get()
                                ->map(fn ($line) => [
                                    'salary_component_id' => $line->salary_component_id,
                                    'monthly_amount' => $line->monthly_amount,
                                ])
                                ->all()),
                        Forms\Components\Textarea::make('remarks')->columnSpanFull(),
                    ])
                    ->modalHeading('Edit Salary Structure')
                    ->modalDescription('Saving creates a new dated version and closes off the current one — prior payroll history is never overwritten.')
                    ->modalSubmitActionLabel('Save')
                    ->action(function (EmployeeSalaryStructure $record, array $data) {
                        $earningAmounts = collect($data['lines'] ?? [])
                            ->mapWithKeys(fn (array $line) => [$line['salary_component_id'] => (float) $line['monthly_amount']])
                            ->all();

                        $deductionAmounts = collect($data['deduction_lines'] ?? [])
                            ->mapWithKeys(fn (array $line) => [$line['salary_component_id'] => (float) $line['monthly_amount']])
                            ->all();

                        app(SalaryStructureService::class)->assign(
                            $record->employee,
                            $data['effective_from'],
                            (float) $data['annual_ctc'],
                            $earningAmounts,
                            auth()->id(),
                            $data['remarks'] ?? null,
                            $deductionAmounts,
                        );

                        Notification::make()->title('Salary structure updated')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    /**
     * Warns when the chosen effective month already has a finalized/locked payroll run for
     * the employee's company — a backdated structure will not rewrite that payslip.
     */
    public static function finalizedMonthWarning(?string $date, ?Employee $employee): ?HtmlString
    {
        if (blank($date) || ! $employee) {
            return null;
        }

        $month = Carbon::parse($date)->format('Y-m');

        $exists = PayrollRun::query()
            ->where('company_id', $employee->company_id)
            ->where('payroll_month', $month)
            ->whereIn('status', [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_LOCKED])
            ->exists();

        if (! $exists) {
            return null;
        }

        return new HtmlString(
            '<span class="text-warning-600 dark:text-warning-400 text-sm">⚠ Payroll for '.$month
            .' is already finalized. This backdated structure will not change that payslip — raise an '
            .'<strong>Arrears</strong> payroll input for the difference.</span>'
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeSalaryStructures::route('/'),
            'create' => Pages\CreateEmployeeSalaryStructure::route('/create'),
            'view' => Pages\ViewEmployeeSalaryStructure::route('/{record}'),
        ];
    }
}
