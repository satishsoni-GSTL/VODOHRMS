<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasApprovalActions;
use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\EmployeeLoanResource\Pages;
use App\Models\EmployeeLoan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeLoanResource extends Resource
{
    use HasApprovalActions;

    protected static ?string $model = EmployeeLoan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Loans & Advances';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'employee_code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->employee_code} - {$record->full_name}")
                    ->searchable(['employee_code', 'first_name', 'last_name'])
                    ->preload()
                    ->required()
                    ->default(fn () => auth()->user()->employee_id)
                    ->disabled(fn () => ! auth()->user()->can('loan.manage'))
                    ->dehydrated(),
                Forms\Components\Select::make('type')
                    ->options([EmployeeLoan::TYPE_LOAN => 'Loan', EmployeeLoan::TYPE_SALARY_ADVANCE => 'Salary Advance'])
                    ->required(),
                Forms\Components\TextInput::make('requested_amount')->numeric()->required()->prefix('₹'),
                Forms\Components\DatePicker::make('request_date')->default(now())->required(),
                Forms\Components\Textarea::make('reason')->required()->columnSpanFull(),
                Forms\Components\Section::make('Processing (HR/Finance)')
                    ->schema([
                        Forms\Components\TextInput::make('approved_amount')->numeric()->prefix('₹'),
                        Forms\Components\TextInput::make('installments')->numeric(),
                        Forms\Components\TextInput::make('monthly_recovery')->numeric()->prefix('₹'),
                        Forms\Components\TextInput::make('recovery_start_month')->placeholder('YYYY-MM'),
                    ])
                    ->visible(fn () => auth()->user()->can('loan.manage'))
                    ->columns(2),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('requested_amount')->money('INR'),
                Tables\Columns\TextColumn::make('approved_amount')->money('INR')->placeholder('—'),
                Tables\Columns\TextColumn::make('outstanding_balance')->money('INR'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => EmployeeLoan::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        EmployeeLoan::STATUS_ACTIVE, EmployeeLoan::STATUS_CLOSED => 'success',
                        EmployeeLoan::STATUS_REJECTED => 'danger',
                        EmployeeLoan::STATUS_SENT_BACK => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(EmployeeLoan::STATUSES),
                Tables\Filters\SelectFilter::make('type')->options([
                    EmployeeLoan::TYPE_LOAN => 'Loan', EmployeeLoan::TYPE_SALARY_ADVANCE => 'Salary Advance',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (EmployeeLoan $record) => ! in_array($record->status, [EmployeeLoan::STATUS_CLOSED, EmployeeLoan::STATUS_REJECTED], true)),
                ...static::approvalActions(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery(), auth()->user(), 'loan.view');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeLoans::route('/'),
            'create' => Pages\CreateEmployeeLoan::route('/create'),
            'edit' => Pages\EditEmployeeLoan::route('/{record}/edit'),
        ];
    }
}
