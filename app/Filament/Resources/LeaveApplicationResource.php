<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasApprovalActions;
use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\LeaveApplicationResource\Pages;
use App\Models\LeaveApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaveApplicationResource extends Resource
{
    use HasApprovalActions;

    protected static ?string $model = LeaveApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Leave';

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
                    ->default(fn () => auth()->user()->employee_id)
                    ->disabled(fn () => ! auth()->user()->can('leave.manage'))
                    ->dehydrated(),
                Forms\Components\Select::make('leave_type_id')->relationship('leaveType', 'name')->required()->searchable()->preload(),
                Forms\Components\DatePicker::make('from_date')->required()->live(),
                Forms\Components\DatePicker::make('to_date')->required(),
                Forms\Components\Toggle::make('is_half_day')->live(),
                Forms\Components\Select::make('half_day_session')
                    ->options(['first_half' => 'First Half', 'second_half' => 'Second Half'])
                    ->visible(fn (Forms\Get $get) => $get('is_half_day')),
                Forms\Components\Textarea::make('reason')->columnSpanFull(),
                Forms\Components\FileUpload::make('attachment_path')->directory('leave-attachments'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('leaveType.name')->label('Type'),
                Tables\Columns\TextColumn::make('from_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('to_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('days'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        LeaveApplication::STATUS_APPROVED => 'success',
                        LeaveApplication::STATUS_REJECTED => 'danger',
                        LeaveApplication::STATUS_SENT_BACK => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    LeaveApplication::STATUS_PENDING => 'Pending',
                    LeaveApplication::STATUS_APPROVED => 'Approved',
                    LeaveApplication::STATUS_REJECTED => 'Rejected',
                    LeaveApplication::STATUS_SENT_BACK => 'Sent Back',
                    LeaveApplication::STATUS_CANCELLED => 'Cancelled',
                ]),
                Tables\Filters\SelectFilter::make('leave_type_id')->relationship('leaveType', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                ...static::approvalActions(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery(), auth()->user(), 'leave.view');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveApplications::route('/'),
            'create' => Pages\CreateLeaveApplication::route('/create'),
            'view' => Pages\ViewLeaveApplication::route('/{record}'),
        ];
    }
}
