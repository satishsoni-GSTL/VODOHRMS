<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OnboardingChecklistResource\Pages;
use App\Models\OnboardingChecklist;
use App\Services\OnboardingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OnboardingChecklistResource extends Resource
{
    protected static ?string $model = OnboardingChecklist::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Onboarding';

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
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\IconColumn::make('personal_details_done')->boolean()->label('Personal'),
                Tables\Columns\IconColumn::make('documents_done')->boolean()->label('Docs'),
                Tables\Columns\IconColumn::make('statutory_done')->boolean()->label('Statutory'),
                Tables\Columns\IconColumn::make('bank_done')->boolean()->label('Bank'),
                Tables\Columns\IconColumn::make('department_done')->boolean()->label('Dept/Mgr'),
                Tables\Columns\IconColumn::make('salary_done')->boolean()->label('Salary'),
                Tables\Columns\IconColumn::make('login_done')->boolean()->label('Login'),
                Tables\Columns\IconColumn::make('asset_allocation_done')->boolean()->label('Assets'),
                Tables\Columns\TextColumn::make('completion_percent')->suffix('%')->weight('bold'),
            ])
            ->defaultSort('completion_percent')
            ->filters([])
            ->actions([
                Tables\Actions\Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (OnboardingChecklist $record) {
                        app(OnboardingService::class)->refresh($record->employee);
                        Notification::make()->title('Checklist refreshed')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnboardingChecklists::route('/'),
            'create' => Pages\CreateOnboardingChecklist::route('/create'),
        ];
    }
}
