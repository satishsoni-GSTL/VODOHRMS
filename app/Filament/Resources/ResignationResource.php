<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasApprovalActions;
use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\ResignationResource\Pages;
use App\Models\Resignation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResignationResource extends Resource
{
    use HasApprovalActions;

    protected static ?string $model = Resignation::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-left-start-on-rectangle';

    protected static ?string $navigationGroup = 'Exit Management';

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
                    ->disabled(fn () => ! auth()->user()->can('resignation.manage'))
                    ->dehydrated(),
                Forms\Components\DatePicker::make('resignation_date')->default(now())->required(),
                Forms\Components\DatePicker::make('requested_last_working_date')->required(),
                Forms\Components\Textarea::make('reason')->required()->columnSpanFull(),
                Forms\Components\Section::make('HR Processing')
                    ->schema([
                        Forms\Components\DatePicker::make('approved_last_working_date'),
                        Forms\Components\Textarea::make('manager_comments'),
                        Forms\Components\Textarea::make('hr_comments'),
                    ])
                    ->visible(fn () => auth()->user()->can('resignation.manage'))
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
                Tables\Columns\TextColumn::make('resignation_date')->date(),
                Tables\Columns\TextColumn::make('requested_last_working_date')->label('Requested LWD')->date(),
                Tables\Columns\TextColumn::make('approved_last_working_date')->label('Approved LWD')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Resignation::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Resignation::STATUS_HR_APPROVED => 'success',
                        Resignation::STATUS_REJECTED => 'danger',
                        Resignation::STATUS_WITHDRAWN => 'gray',
                        default => 'warning',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(Resignation::STATUSES),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Resignation $record) => ! in_array($record->status, [Resignation::STATUS_HR_APPROVED, Resignation::STATUS_REJECTED, Resignation::STATUS_WITHDRAWN], true)),
                ...static::approvalActions(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery(), auth()->user(), 'resignation.view');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResignations::route('/'),
            'create' => Pages\CreateResignation::route('/create'),
            'edit' => Pages\EditResignation::route('/{record}/edit'),
        ];
    }
}
