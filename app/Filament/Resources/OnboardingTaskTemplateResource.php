<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OnboardingTaskTemplateResource\Pages;
use App\Models\OnboardingTaskTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** What has to happen for every new joiner, who does it and when. Access: onboarding.manage. */
class OnboardingTaskTemplateResource extends Resource
{
    protected static ?string $model = OnboardingTaskTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Onboarding Task Templates';

    protected static ?string $navigationGroup = 'Onboarding';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                Forms\Components\Select::make('assignee_type')
                    ->label('Who does it')
                    ->options(OnboardingTaskTemplate::ASSIGNEE_TYPES)
                    ->required()
                    ->live(),
                Forms\Components\Select::make('role_id')
                    ->label('Role')
                    ->relationship('role', 'name')
                    ->visible(fn (Get $get) => $get('assignee_type') === OnboardingTaskTemplate::ASSIGNEE_ROLE)
                    ->required(fn (Get $get) => $get('assignee_type') === OnboardingTaskTemplate::ASSIGNEE_ROLE),
                Forms\Components\Select::make('user_id')
                    ->label('Person')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => $get('assignee_type') === OnboardingTaskTemplate::ASSIGNEE_USER)
                    ->required(fn (Get $get) => $get('assignee_type') === OnboardingTaskTemplate::ASSIGNEE_USER),
                Forms\Components\TextInput::make('due_offset_days')
                    ->label('Due (days from joining)')
                    ->helperText('0 = joining day, -2 = two days before, 7 = a week after.')
                    ->numeric()->integer()->minValue(-90)->maxValue(365)->default(0)->required(),
                Forms\Components\Select::make('company_id')
                    ->label('Only for company')
                    ->relationship('company', 'name')
                    ->placeholder('All companies'),
                Forms\Components\TextInput::make('sequence')->numeric()->integer()->default(100),
                Forms\Components\Toggle::make('is_active')->default(true)->inline(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequence')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('assignee_type')->label('Who')
                    ->formatStateUsing(fn (OnboardingTaskTemplate $record, string $state) => match ($state) {
                        OnboardingTaskTemplate::ASSIGNEE_ROLE => 'Role: '.($record->role?->name ?? '—'),
                        OnboardingTaskTemplate::ASSIGNEE_USER => $record->user?->name ?? '—',
                        OnboardingTaskTemplate::ASSIGNEE_HR => 'HR',
                        OnboardingTaskTemplate::ASSIGNEE_MANAGER => 'Reporting manager',
                        OnboardingTaskTemplate::ASSIGNEE_EMPLOYEE => 'New joiner',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('due_label')->label('When'),
                Tables\Columns\TextColumn::make('company.name')->placeholder('All'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sequence')
            ->reorderable('sequence')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnboardingTaskTemplates::route('/'),
            'create' => Pages\CreateOnboardingTaskTemplate::route('/create'),
            'edit' => Pages\EditOnboardingTaskTemplate::route('/{record}/edit'),
        ];
    }
}
