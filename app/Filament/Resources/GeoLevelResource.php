<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByFieldForce;
use App\Filament\Resources\GeoLevelResource\Pages;
use App\Models\GeoLevel;
use App\Models\HierarchyLevel;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GeoLevelResource extends Resource
{
    use GatedByFieldForce;

    protected static ?string $model = GeoLevel::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-arrow-down';

    protected static ?string $navigationLabel = 'Geography Levels';

    protected static ?string $modelLabel = 'Geography Level';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(100)
                    ->helperText('e.g. Zone, Region, Area, Headquarter, Territory'),
                Forms\Components\TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('depth')
                    ->label('Level (1 = top)')
                    ->numeric()->minValue(1)->maxValue(20)->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('depth')->label('Level')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('geographies_count')->counts('geographies')->label('Nodes'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('depth')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (GeoLevel $record) => $record->geographies()->withTrashed()->exists()),
            ]);
    }

    public static function listHeaderActions(): array
    {
        return [
            Action::make('loadPharmaDefaults')
                ->label('Load standard pharma levels')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('fieldforce.manage') ?? false)
                ->requiresConfirmation()
                ->modalDescription('Adds Zone > Region > Area > Headquarter > Territory and the NSM > ZBM > RBM > ABM > MR ladder. Only missing entries are added; nothing existing is changed.')
                ->action(function () {
                    $created = HierarchyLevel::loadPharmaDefaults();
                    Notification::make()->success()->title("{$created} level(s) added")->send();
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeoLevels::route('/'),
            'create' => Pages\CreateGeoLevel::route('/create'),
            'edit' => Pages\EditGeoLevel::route('/{record}/edit'),
        ];
    }
}
