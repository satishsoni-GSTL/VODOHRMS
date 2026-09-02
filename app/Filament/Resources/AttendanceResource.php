<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Attendance';

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
                    ->required(),
                Forms\Components\DatePicker::make('attendance_date')->required(),
                Forms\Components\TimePicker::make('first_in')->seconds(false),
                Forms\Components\TimePicker::make('last_out')->seconds(false),
                Forms\Components\Select::make('status')
                    ->options(Attendance::STATUSES)
                    ->required(),
                Forms\Components\Select::make('source')
                    ->options(['manual' => 'Manual', 'biometric' => 'Biometric', 'import' => 'Import', 'self' => 'Self'])
                    ->default('manual')
                    ->required(),
                Forms\Components\Textarea::make('remarks')->columnSpanFull(),
                Forms\Components\Toggle::make('is_frozen')->default(false)
                    ->helperText('Frozen attendance cannot be edited without reopening (e.g. after payroll run).'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('attendance_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('first_in')->time('H:i'),
                Tables\Columns\TextColumn::make('last_out')->label('Last Out')->time('H:i')->placeholder('—')
                    ->state(fn (Attendance $record) => $record->display_last_out),
                Tables\Columns\TextColumn::make('effective_hours')->label('Hours')
                    ->formatStateUsing(fn ($state) => Attendance::formatHours($state) ?? '—'),
                Tables\Columns\TextColumn::make('late_minutes')->label('Late (min)')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Attendance::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Attendance::STATUS_PRESENT => 'success',
                        Attendance::STATUS_ABSENT, Attendance::STATUS_MISSING_PUNCH => 'danger',
                        Attendance::STATUS_HALF_DAY => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_frozen')->boolean()->label('Frozen')->toggleable(),
            ])
            ->defaultSort('attendance_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(Attendance::STATUSES),
                Tables\Filters\Filter::make('attendance_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'import' => Pages\ImportAttendance::route('/import'),
            'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}
