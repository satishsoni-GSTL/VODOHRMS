<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasApprovalActions;
use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\TravelRequestResource\Pages;
use App\Models\TravelRequest;
use App\Services\TravelService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TravelRequestResource extends Resource
{
    use HasApprovalActions;

    protected static ?string $model = TravelRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Travel Requests';

    protected static ?string $navigationGroup = 'Expenses';

    protected static ?int $navigationSort = 1;

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

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
                Forms\Components\Select::make('travel_type')->options(TravelRequest::TYPES)->default('domestic')->required(),
                Forms\Components\Textarea::make('purpose')->required()->rows(2)->columnSpanFull(),
                Forms\Components\TextInput::make('from_location')->label('From')->required()->maxLength(120),
                Forms\Components\TextInput::make('to_location')->label('To')->required()->maxLength(120),
                Forms\Components\DatePicker::make('departure_date')->required()->minDate(now()->startOfDay())->live(),
                Forms\Components\DatePicker::make('return_date')->required()->afterOrEqual('departure_date'),
                Forms\Components\Select::make('travel_mode')->label('Mode')->options(TravelRequest::MODES)->required(),
                Forms\Components\Toggle::make('accommodation_required')->inline(false),
                Forms\Components\TextInput::make('estimated_cost')->label('Estimated total cost')->numeric()->minValue(0)->prefix('₹'),
                Forms\Components\TextInput::make('advance_requested')->label('Advance needed')->numeric()->minValue(0)->default(0)->prefix('₹')
                    ->helperText('Requests with an advance also go to Finance for approval.'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request_number')->label('No.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('route_label')->label('Route'),
                Tables\Columns\TextColumn::make('departure_date')->label('Departs')->date()->sortable(),
                Tables\Columns\TextColumn::make('return_date')->label('Returns')->date(),
                Tables\Columns\TextColumn::make('estimated_cost')->money('INR')->label('Estimate')->toggleable(),
                Tables\Columns\TextColumn::make('advance_requested')->money('INR')->label('Advance'),
                Tables\Columns\TextColumn::make('advance_paid')->money('INR')->label('Advance Paid')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => TravelRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        TravelRequest::STATUS_APPROVED => 'success',
                        TravelRequest::STATUS_REJECTED => 'danger',
                        TravelRequest::STATUS_SENT_BACK => 'warning',
                        TravelRequest::STATUS_CLOSED => 'info',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(TravelRequest::STATUSES),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->extraModalFooterActions(fn () => static::approvalActions()),
                ...static::approvalActions(),
                static::payAdvanceAction(),
                static::closeAction(),
                static::cancelAction(),
                static::reapplyAction(),
            ])
            ->bulkActions([]);
    }

    /** @param  class-string  $actionClass */
    public static function payAdvanceAction(string $actionClass = Tables\Actions\Action::class)
    {
        return $actionClass::make('payAdvance')
            ->label('Pay Advance')
            ->icon('heroicon-o-currency-rupee')
            ->color('success')
            ->visible(fn (TravelRequest $record) => $record->status === TravelRequest::STATUS_APPROVED
                && (float) $record->advance_requested > 0
                && (float) $record->advance_paid == 0
                && auth()->user()->can('expense.process'))
            ->form([
                Forms\Components\TextInput::make('paid_amount')->numeric()->required()->prefix('₹')
                    ->default(fn (TravelRequest $record) => $record->advance_requested),
                Forms\Components\DatePicker::make('paid_on')->default(now())->required(),
                Forms\Components\TextInput::make('reference')->label('Reference / UTR'),
            ])
            ->action(fn (TravelRequest $record, array $data) => static::run(fn () => app(TravelService::class)->recordAdvancePayment(
                $record, (float) $data['paid_amount'], $data['paid_on'], $data['reference'] ?? null, auth()->user(),
            ), 'Advance payment recorded'));
    }

    /** @param  class-string  $actionClass */
    public static function closeAction(string $actionClass = Tables\Actions\Action::class)
    {
        return $actionClass::make('close')
            ->label('Close Trip')
            ->icon('heroicon-o-lock-closed')
            ->color('gray')
            ->visible(fn (TravelRequest $record) => $record->status === TravelRequest::STATUS_APPROVED
                && auth()->user()->can('expense.process'))
            ->modalDescription(fn (TravelRequest $record) => ($o = $record->outstandingAdvance()) > 0
                ? '₹'.number_format($o, 2).' of the advance has not been adjusted against a paid claim. Note how it was recovered.'
                : 'Mark this trip as settled.')
            ->form([Forms\Components\Textarea::make('closure_remarks')->label('Remarks')])
            ->action(fn (TravelRequest $record, array $data) => static::run(
                fn () => app(TravelService::class)->close($record, $data['closure_remarks'] ?? null),
                'Trip closed',
            ));
    }

    /** @param  class-string  $actionClass */
    public static function cancelAction(string $actionClass = Tables\Actions\Action::class)
    {
        return $actionClass::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (TravelRequest $record) => in_array($record->status, [TravelRequest::STATUS_PENDING, TravelRequest::STATUS_SENT_BACK], true)
                && ((int) $record->employee_id === (int) auth()->user()->employee_id || auth()->user()->can('expense.manage')))
            ->action(fn (TravelRequest $record) => static::run(fn () => app(TravelService::class)->cancel($record), 'Travel request cancelled'));
    }

    private static function run(callable $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->title($success)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery()->with(['employee', 'approvalInstance']), auth()->user(), 'expense.view');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTravelRequests::route('/'),
            'create' => Pages\CreateTravelRequest::route('/create'),
            'view' => Pages\ViewTravelRequest::route('/{record}'),
        ];
    }
}
