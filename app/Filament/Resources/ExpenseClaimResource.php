<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasApprovalActions;
use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\ExpenseClaimResource\Pages;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Services\ExpenseClaimService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseClaimResource extends Resource
{
    use HasApprovalActions;

    protected static ?string $model = ExpenseClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Expenses';

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
                    ->required()
                    ->default(fn () => auth()->user()->employee_id)
                    ->disabled(fn () => ! auth()->user()->can('expense.manage'))
                    ->dehydrated(),
                Forms\Components\DatePicker::make('claim_date')->default(now())->required(),
                Forms\Components\TextInput::make('project_client')->maxLength(150),
                Forms\Components\Repeater::make('lines')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(fn () => ExpenseCategory::query()->active()->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\DatePicker::make('expense_date')->required(),
                        Forms\Components\TextInput::make('requested_amount')->numeric()->required()->prefix('₹'),
                        Forms\Components\TextInput::make('vendor')->maxLength(150),
                        Forms\Components\TextInput::make('bill_number')->maxLength(100),
                        Forms\Components\Select::make('payment_mode')
                            ->options(['cash' => 'Cash', 'card' => 'Card', 'upi' => 'UPI', 'other' => 'Other']),
                        Forms\Components\FileUpload::make('receipt_path')
                            ->directory('expense-receipts')
                            ->disk('local')
                            ->visibility('private'),
                        Forms\Components\Textarea::make('description')->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('claim_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('claim_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('total_requested_amount')->money('INR')->label('Requested'),
                Tables\Columns\TextColumn::make('total_approved_amount')->money('INR')->label('Approved'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ExpenseClaim::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        ExpenseClaim::STATUS_APPROVED, ExpenseClaim::STATUS_PAID => 'success',
                        ExpenseClaim::STATUS_REJECTED => 'danger',
                        ExpenseClaim::STATUS_SENT_BACK => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ExpenseClaim::STATUSES),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                ...static::approvalActions(),
                Tables\Actions\Action::make('recordPayment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-currency-rupee')
                    ->color('success')
                    ->visible(fn (ExpenseClaim $record) => $record->status === ExpenseClaim::STATUS_APPROVED
                        && auth()->user()->can('expense.process')
                        && ! $record->payment()->exists())
                    ->form([
                        Forms\Components\TextInput::make('paid_amount')->numeric()->required()
                            ->default(fn (ExpenseClaim $record) => $record->total_approved_amount),
                        Forms\Components\DatePicker::make('paid_on')->default(now())->required(),
                        Forms\Components\TextInput::make('payment_reference')->label('Reference / UTR'),
                    ])
                    ->action(function (ExpenseClaim $record, array $data) {
                        app(ExpenseClaimService::class)->recordPayment(
                            $record, (float) $data['paid_amount'], $data['paid_on'],
                            $data['payment_reference'] ?? null, auth()->id(),
                        );
                        Notification::make()->title('Payment recorded')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery(), auth()->user(), 'expense.view');
    }

    protected static function approvalModalContent(Model $record): ?ViewContract
    {
        if (! $record instanceof ExpenseClaim) {
            return null;
        }

        return view('filament.expense-claims.approval-details', [
            'claim' => $record->loadMissing('lines.category'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseClaims::route('/'),
            'create' => Pages\CreateExpenseClaim::route('/create'),
            'view' => Pages\ViewExpenseClaim::route('/{record}'),
        ];
    }
}
