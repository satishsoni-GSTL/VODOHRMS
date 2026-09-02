<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasApprovalActions;
use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\AttendanceRegularizationResource\Pages;
use App\Models\AttendanceRegularization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceRegularizationResource extends Resource
{
    use HasApprovalActions;

    protected static ?string $model = AttendanceRegularization::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 6;

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
                Forms\Components\DatePicker::make('attendance_date')->required(),
                Forms\Components\Select::make('request_type')->options(AttendanceRegularization::TYPES)->required(),
                Forms\Components\TimePicker::make('requested_values.first_in')->label('Corrected Check-In')->seconds(false),
                Forms\Components\TimePicker::make('requested_values.last_out')->label('Corrected Check-Out')->seconds(false),
                Forms\Components\Textarea::make('reason')->required()->columnSpanFull(),
                Forms\Components\FileUpload::make('attachment_path')->directory('regularization-attachments'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('attendance_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('request_type')->formatStateUsing(fn (string $state) => AttendanceRegularization::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('reason')->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        AttendanceRegularization::STATUS_APPROVED => 'success',
                        AttendanceRegularization::STATUS_REJECTED => 'danger',
                        AttendanceRegularization::STATUS_SENT_BACK => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    AttendanceRegularization::STATUS_PENDING => 'Pending',
                    AttendanceRegularization::STATUS_MANAGER_APPROVED => 'Manager Approved',
                    AttendanceRegularization::STATUS_HR_APPROVED => 'HR Approved',
                    AttendanceRegularization::STATUS_APPROVED => 'Approved',
                    AttendanceRegularization::STATUS_REJECTED => 'Rejected',
                    AttendanceRegularization::STATUS_SENT_BACK => 'Sent Back',
                ]),
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
            'index' => Pages\ListAttendanceRegularizations::route('/'),
            'create' => Pages\CreateAttendanceRegularization::route('/create'),
            'view' => Pages\ViewAttendanceRegularization::route('/{record}'),
        ];
    }
}
