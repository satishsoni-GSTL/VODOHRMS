<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FestiveThemeResource\Pages;
use App\Models\FestiveTheme;
use App\Support\FestiveThemes;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Organization → Celebration Themes: switch festival / company themes on, schedule them,
 * edit the greeting and countdown days, and preview before going live.
 */
class FestiveThemeResource extends Resource
{
    protected static ?string $model = FestiveTheme::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Celebration Themes';

    protected static ?string $modelLabel = 'celebration theme';

    protected static ?string $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 90;

    private static function canManage(): bool
    {
        return auth()->user()?->can('organization.manage') ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canManage();
    }

    public static function canCreate(): bool
    {
        return false; // the list of themes is fixed in code
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        $placeholders = 'Placeholders: {name} (employee first name), {company}, {year}, {venue}. Leave empty to use the default.';

        return $form
            ->schema([
                Forms\Components\Section::make(fn (?FestiveTheme $record) => $record?->definition()['name'] ?? 'Theme')
                    ->description(fn (?FestiveTheme $record) => $record?->definition()['description'])
                    ->schema([
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Switched on')
                            ->helperText(fn (?FestiveTheme $record) => $record?->isPersonal()
                                ? 'While on, every employee sees this on their own birthday (date of birth from their profile).'
                                : 'Shows between the dates below. With no dates it stays on until you switch it off.'),
                        Forms\Components\DatePicker::make('starts_on')->label('Show from')
                            ->hidden(fn (?FestiveTheme $record) => $record?->isPersonal()),
                        Forms\Components\DatePicker::make('ends_on')->label('Show until')->afterOrEqual('starts_on')
                            ->hidden(fn (?FestiveTheme $record) => $record?->isPersonal()),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Greeting')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->placeholder(fn (?FestiveTheme $record) => $record?->definition()['title'])
                            ->maxLength(150)
                            ->helperText($placeholders),
                        Forms\Components\Textarea::make('message')
                            ->placeholder(fn (?FestiveTheme $record) => $record?->definition()['message'])
                            ->rows(2)
                            ->maxLength(500),
                        Forms\Components\TextInput::make('venue')
                            ->label('Venue')
                            ->placeholder('e.g. The Grand Ballroom, Pune')
                            ->maxLength(200)
                            ->visible(fn (?FestiveTheme $record) => $record?->key === 'annual_day'),
                    ]),

                Forms\Components\Section::make('Countdown days')
                    ->description('Shown on the login page and dashboard with a "5 days to …" countdown.')
                    ->hidden(fn (?FestiveTheme $record) => $record?->isPersonal())
                    ->schema([
                        Forms\Components\Repeater::make('days')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\TextInput::make('name')->required()->maxLength(60),
                                Forms\Components\DatePicker::make('date')->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add day')
                            ->reorderable(false)
                            ->live(),
                        Forms\Components\Select::make('main_day')
                            ->label('Main day')
                            ->helperText('Highlighted in the Diwali day strip.')
                            ->options(fn (Get $get) => collect($get('days') ?? [])->pluck('name', 'name')->filter()->all())
                            ->visible(fn (?FestiveTheme $record) => $record?->key === 'diwali'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Theme')
                    ->state(fn (FestiveTheme $record) => $record->definition()['name'] ?? $record->key)
                    ->description(fn (FestiveTheme $record) => $record->definition()['description'] ?? null)
                    ->weight('bold')
                    ->wrap(),
                Tables\Columns\ToggleColumn::make('is_enabled')->label('On'),
                Tables\Columns\TextColumn::make('window')
                    ->label('When')
                    ->state(fn (FestiveTheme $record) => match (true) {
                        $record->isPersonal() => 'Each employee’s birthday',
                        $record->starts_on && $record->ends_on => $record->starts_on->format('d M Y').' – '.$record->ends_on->format('d M Y'),
                        $record->starts_on !== null => 'From '.$record->starts_on->format('d M Y'),
                        $record->ends_on !== null => 'Until '.$record->ends_on->format('d M Y'),
                        default => 'No dates (manual)',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->state(fn (FestiveTheme $record) => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state) => match (true) {
                        $state === 'Live now', str_starts_with($state, 'On') => 'success',
                        $state === 'Scheduled' => 'info',
                        str_starts_with($state, 'Overlaps') => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('id')
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (FestiveTheme $record) => url('/admin').'?festive_preview='.$record->key)
                    ->tooltip('See it on your dashboard (only you). End the preview from the banner or the button at the top.'),
                Tables\Actions\Action::make('previewLogin')
                    ->label('Login page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (FestiveTheme $record) => route('filament.admin.auth.login').'?festive_preview='.$record->key)
                    ->openUrlInNewTab()
                    ->visible(fn (FestiveTheme $record) => ! $record->isPersonal())
                    ->tooltip('Opens the sign-in page with this theme (in a private window you see it as everyone will).'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFestiveThemes::route('/'),
            'edit' => Pages\EditFestiveTheme::route('/{record}/edit'),
        ];
    }
}
