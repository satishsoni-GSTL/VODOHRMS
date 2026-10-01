<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\OptionalHolidayClaimResource\Pages;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OptionalHolidayClaim;
use App\Services\OptionalHolidayService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class OptionalHolidayClaimResource extends Resource
{
    protected static ?string $model = OptionalHolidayClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Optional Holidays';

    protected static ?string $modelLabel = 'optional holiday claim';

    protected static ?int $navigationSort = 3;

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
                    ->live()
                    ->default(fn () => auth()->user()->employee_id)
                    ->disabled(fn () => ! auth()->user()->can('attendance.manage'))
                    ->dehydrated(),
                Forms\Components\Select::make('holiday_id')
                    ->label('Optional Holiday')
                    ->required()
                    ->options(function (Get $get) {
                        $employee = Employee::find($get('employee_id'));

                        return $employee
                            ? app(OptionalHolidayService::class)->claimableHolidays($employee)
                                ->mapWithKeys(fn (Holiday $h) => [$h->id => $h->date->format('d M Y (D)')." — {$h->name}"])
                            : [];
                    })
                    ->helperText(function (Get $get) {
                        $employee = Employee::find($get('employee_id'));

                        if (! $employee) {
                            return null;
                        }

                        $service = app(OptionalHolidayService::class);
                        $year = (int) now()->format('Y');

                        return "Used {$service->usedFor($employee, $year)} of {$service->limitFor($employee, $year)} optional holidays in {$year}.";
                    }),
                Forms\Components\Textarea::make('reason')->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('holiday.name')->label('Holiday'),
                Tables\Columns\TextColumn::make('holiday.date')->label('Date')->date()->sortable(),
                Tables\Columns\TextColumn::make('reason')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OptionalHolidayClaim::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === OptionalHolidayClaim::STATUS_CLAIMED ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')->label('Claimed On')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(OptionalHolidayClaim::STATUSES),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OptionalHolidayClaim $record) => $record->status === OptionalHolidayClaim::STATUS_CLAIMED
                        && $record->holiday->date->isFuture()
                        && ($record->employee_id === auth()->user()->employee_id || auth()->user()->can('attendance.manage')))
                    ->action(function (OptionalHolidayClaim $record) {
                        try {
                            app(OptionalHolidayService::class)->cancel($record);
                            Notification::make()->title('Optional holiday cancelled')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery()->with(['employee', 'holiday']), auth()->user(), 'attendance.view');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOptionalHolidayClaims::route('/'),
            'create' => Pages\CreateOptionalHolidayClaim::route('/create'),
        ];
    }
}
