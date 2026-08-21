<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasApprovalActions;
use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\WorkFromHomeRequestResource\Pages;
use App\Models\WorkFromHomeRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkFromHomeRequestResource extends Resource
{
    use HasApprovalActions;

    protected static ?string $model = WorkFromHomeRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Work From Home';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 7;

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
                    ->disabled(fn () => ! auth()->user()->can('attendance.manage'))
                    ->dehydrated(),
                Forms\Components\DatePicker::make('from_date')->required()->live()->minDate(now()->startOfDay()),
                Forms\Components\DatePicker::make('to_date')->required()
                    ->afterOrEqual('from_date')
                    ->minDate(fn (Forms\Get $get) => $get('from_date') ?: now()->startOfDay()),
                Forms\Components\Textarea::make('reason')->required()->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('from_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('to_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('total_days')->label('Working Days')->state(fn (WorkFromHomeRequest $record) => $record->total_days),
                Tables\Columns\TextColumn::make('reason')->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => WorkFromHomeRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        WorkFromHomeRequest::STATUS_APPROVED => 'success',
                        WorkFromHomeRequest::STATUS_REJECTED => 'danger',
                        WorkFromHomeRequest::STATUS_SENT_BACK => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(WorkFromHomeRequest::STATUSES),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                ...static::approvalActions(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery(), auth()->user(), 'attendance.view');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkFromHomeRequests::route('/'),
            'create' => Pages\CreateWorkFromHomeRequest::route('/create'),
            'view' => Pages\ViewWorkFromHomeRequest::route('/{record}'),
        ];
    }
}
