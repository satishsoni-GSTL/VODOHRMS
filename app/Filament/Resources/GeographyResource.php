<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByFieldForce;
use App\Filament\Resources\GeographyResource\Pages;
use App\Models\Geography;
use App\Models\GeoLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GeographyResource extends Resource
{
    use GatedByFieldForce;

    protected static ?string $model = Geography::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Geography';

    protected static ?string $pluralModelLabel = 'Geography';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('geo_level_id')
                    ->label('Level')
                    ->relationship('level', 'name', fn (Builder $q) => $q->orderBy('depth'))
                    ->preload()->required()->live(),
                Forms\Components\Select::make('parent_id')
                    ->label('Parent')
                    ->helperText('The node one level above (e.g. the Area this HQ belongs to).')
                    ->options(function (Forms\Get $get, ?Geography $record) {
                        $level = GeoLevel::find($get('geo_level_id'));

                        return Geography::query()
                            ->with('level')
                            ->when($level, fn (Builder $q) => $q->whereHas('level', fn (Builder $l) => $l->where('depth', '<', $level->depth)))
                            ->when($record, fn (Builder $q) => $q->whereNotIn('id', $record->descendantAndSelfIds()))
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Geography $g) => [$g->id => "{$g->name} ({$g->level?->name})"]);
                    })
                    ->searchable()
                    ->rules(fn (?Geography $record) => [
                        fn (string $attribute, $value, \Closure $fail) => $record?->wouldCreateCycle($value ? (int) $value : null)
                            ? $fail('A geography cannot sit under itself or one of its own children.')
                            : null,
                    ]),
                Forms\Components\TextInput::make('name')->required()->maxLength(150),
                Forms\Components\TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('state')->maxLength(100),
                Forms\Components\TextInput::make('city')->maxLength(100),
                Forms\Components\Select::make('station_type')
                    ->options(Geography::STATION_TYPES)
                    ->helperText('Used for daily-allowance classification of territories / beats.'),
                Forms\Components\Select::make('company_id')
                    ->relationship('company', 'name', fn (Builder $q) => $q->where('has_field_force', true))
                    ->preload(),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('level.name')->label('Level')->badge()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('path')->label('Hierarchy')->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('station_type')
                    ->formatStateUsing(fn (?string $state) => Geography::STATION_TYPES[$state] ?? $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('state')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('children_count')->counts('children')->label('Children'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('geo_level_id')->relationship('level', 'name')->label('Level'),
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Under')
                    ->options(fn () => Geography::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $q, array $data) => $data['value']
                        ? $q->whereIn('id', array_diff(Geography::find($data['value'])?->descendantAndSelfIds() ?? [], [(int) $data['value']]))
                        : $q),
                Tables\Filters\SelectFilter::make('station_type')->options(Geography::STATION_TYPES),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['level', 'parent.parent.parent.parent']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeographies::route('/'),
            'create' => Pages\CreateGeography::route('/create'),
            'edit' => Pages\EditGeography::route('/{record}/edit'),
        ];
    }
}
