<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeLeaveBalanceResource\Pages;
use App\Models\EmployeeLeaveBalance;
use App\Services\LeaveBalanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeLeaveBalanceResource extends Resource
{
    protected static ?string $model = EmployeeLeaveBalance::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Leave';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Leave Balances';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')->relationship('employee', 'employee_code')->required()->searchable()->preload(),
            Forms\Components\Select::make('leave_type_id')->relationship('leaveType', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('year')->numeric()->default(now()->year)->required(),
            Forms\Components\TextInput::make('opening_balance')->numeric()->default(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('leaveType.name')->label('Leave Type'),
                Tables\Columns\TextColumn::make('year')->sortable(),
                Tables\Columns\TextColumn::make('opening_balance'),
                Tables\Columns\TextColumn::make('credited'),
                Tables\Columns\TextColumn::make('used'),
                Tables\Columns\TextColumn::make('closing_balance')->weight('bold'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('leave_type_id')->relationship('leaveType', 'name')->label('Leave Type'),
                Tables\Filters\SelectFilter::make('year')->options(fn () => collect(range(now()->year - 2, now()->year + 1))->mapWithKeys(fn ($y) => [$y => $y])),
            ])
            ->actions([
                Tables\Actions\Action::make('credit')
                    ->label('Credit Days')
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn () => auth()->user()->can('organization.manage'))
                    ->form([
                        Forms\Components\TextInput::make('days')->numeric()->required()->label('Days to credit'),
                        Forms\Components\TextInput::make('remarks')->label('Remarks'),
                    ])
                    ->action(function (EmployeeLeaveBalance $record, array $data) {
                        app(LeaveBalanceService::class)->credit(
                            $record->employee, $record->leaveType, $record->year,
                            (float) $data['days'], $data['remarks'] ?? 'Manual credit'
                        );
                        Notification::make()->title('Balance credited')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeLeaveBalances::route('/'),
            'create' => Pages\CreateEmployeeLeaveBalance::route('/create'),
            'edit' => Pages\EditEmployeeLeaveBalance::route('/{record}/edit'),
        ];
    }
}
