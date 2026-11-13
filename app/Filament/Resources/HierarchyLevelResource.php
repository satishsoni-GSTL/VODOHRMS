<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByFieldForce;
use App\Filament\Resources\HierarchyLevelResource\Pages;
use App\Models\HierarchyLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HierarchyLevelResource extends Resource
{
    use GatedByFieldForce;

    protected static ?string $model = HierarchyLevel::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Hierarchy Levels';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(150)
                    ->helperText('e.g. Medical Representative, Area Business Manager'),
                Forms\Components\TextInput::make('short_name')->required()->maxLength(20)->unique(ignoreRecord: true)
                    ->helperText('e.g. MR, ABM, RBM, ZBM, NSM'),
                Forms\Components\TextInput::make('rank')
                    ->label('Rank (1 = most senior)')
                    ->numeric()->minValue(1)->maxValue(50)->required(),
                Forms\Components\Select::make('workforce_type')
                    ->options(HierarchyLevel::WORKFORCE_TYPES)->default('field')->required(),
                Forms\Components\Select::make('geo_level_id')
                    ->label('Covers geography level')
                    ->relationship('geoLevel', 'name', fn (Builder $q) => $q->orderBy('depth'))
                    ->preload()
                    ->helperText('The level of geography one person at this rung is responsible for.'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rank')->sortable(),
                Tables\Columns\TextColumn::make('short_name')->badge(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('workforce_type')
                    ->formatStateUsing(fn (string $state) => HierarchyLevel::WORKFORCE_TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('geoLevel.name')->label('Covers'),
                Tables\Columns\TextColumn::make('positions_count')->counts('positions')->label('Positions'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('rank')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (HierarchyLevel $record) => $record->positions()->withTrashed()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHierarchyLevels::route('/'),
            'create' => Pages\CreateHierarchyLevel::route('/create'),
            'edit' => Pages\EditHierarchyLevel::route('/{record}/edit'),
        ];
    }
}
