<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FullFinalSettlementResource\Pages;
use App\Models\FullFinalSettlement;
use App\Services\FnFSettlementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FullFinalSettlementResource extends Resource
{
    protected static ?string $model = FullFinalSettlement::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Exit Management';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Auto-Computed')
                    ->schema([
                        Forms\Components\TextInput::make('leave_encashment')->numeric()->prefix('₹')->disabled(),
                        Forms\Components\TextInput::make('loan_recovery')->numeric()->prefix('₹')->disabled(),
                        Forms\Components\TextInput::make('advance_recovery')->numeric()->prefix('₹')->disabled(),
                        Forms\Components\TextInput::make('reimbursement')->numeric()->prefix('₹')->disabled(),
                        Forms\Components\TextInput::make('notice_recovery')->numeric()->prefix('₹')->disabled(),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('HR Editable')
                    ->schema([
                        Forms\Components\TextInput::make('pending_salary')->numeric()->prefix('₹'),
                        Forms\Components\TextInput::make('bonus_incentive')->numeric()->prefix('₹'),
                        Forms\Components\TextInput::make('other_earnings')->numeric()->prefix('₹'),
                        Forms\Components\TextInput::make('tds')->numeric()->prefix('₹'),
                        Forms\Components\TextInput::make('other_deductions')->numeric()->prefix('₹'),
                    ])
                    ->columns(3),
                Forms\Components\TextInput::make('final_amount')->numeric()->prefix('₹')->disabled()->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('final_amount')->money('INR')->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        FullFinalSettlement::STATUS_PAID => 'success',
                        FullFinalSettlement::STATUS_APPROVED => 'info',
                        FullFinalSettlement::STATUS_CALCULATED => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    FullFinalSettlement::STATUS_DRAFT => 'Draft',
                    FullFinalSettlement::STATUS_CALCULATED => 'Calculated',
                    FullFinalSettlement::STATUS_APPROVED => 'Approved',
                    FullFinalSettlement::STATUS_PAID => 'Paid',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalculate')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (FullFinalSettlement $record) => in_array($record->status, [FullFinalSettlement::STATUS_DRAFT, FullFinalSettlement::STATUS_CALCULATED], true) && auth()->user()->can('fnf.process'))
                    ->action(function (FullFinalSettlement $record) {
                        app(FnFSettlementService::class)->calculate($record->resignation, auth()->user());
                        Notification::make()->title('Settlement recalculated')->success()->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (FullFinalSettlement $record) => $record->status === FullFinalSettlement::STATUS_CALCULATED && auth()->user()->can('fnf.process')),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (FullFinalSettlement $record) => $record->status === FullFinalSettlement::STATUS_CALCULATED && auth()->user()->can('fnf.process'))
                    ->requiresConfirmation()
                    ->action(function (FullFinalSettlement $record) {
                        app(FnFSettlementService::class)->approve($record, auth()->user());
                        Notification::make()->title('Settlement approved')->success()->send();
                    }),
                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-currency-rupee')
                    ->color('success')
                    ->visible(fn (FullFinalSettlement $record) => $record->status === FullFinalSettlement::STATUS_APPROVED && auth()->user()->can('fnf.process'))
                    ->requiresConfirmation()
                    ->action(function (FullFinalSettlement $record) {
                        app(FnFSettlementService::class)->markPaid($record);
                        Notification::make()->title('Settlement marked paid; employee exited')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->can('fnf.process') || $user->can('resignation.view')) {
            return $query;
        }

        return $query->where('employee_id', $user->employee_id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFullFinalSettlements::route('/'),
            'edit' => Pages\EditFullFinalSettlement::route('/{record}/edit'),
        ];
    }
}
