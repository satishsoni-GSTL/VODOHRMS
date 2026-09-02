<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeAssetResource\Pages;
use App\Models\EmployeeAsset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeAssetResource extends Resource
{
    protected static ?string $model = EmployeeAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationGroup = 'Onboarding';

    protected static ?int $navigationSort = 2;

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
                Forms\Components\TextInput::make('asset_type')->required()->maxLength(150)->placeholder('Laptop, ID Card, SIM, ...'),
                Forms\Components\TextInput::make('asset_tag')->maxLength(100),
                Forms\Components\DatePicker::make('allocated_on')->default(now())->required(),
                Forms\Components\DatePicker::make('returned_on'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('allocated_on', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('asset_type')->searchable(),
                Tables\Columns\TextColumn::make('asset_tag')->searchable(),
                Tables\Columns\TextColumn::make('allocated_on')->date(),
                Tables\Columns\TextColumn::make('returned_on')->date()->placeholder('Not returned'),
            ])
            ->filters([
                Tables\Filters\Filter::make('not_returned')
                    ->label('Not Yet Returned')
                    ->query(fn ($query) => $query->whereNull('returned_on'))
                    ->toggle(),
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
            'index' => Pages\ListEmployeeAssets::route('/'),
            'create' => Pages\CreateEmployeeAsset::route('/create'),
            'edit' => Pages\EditEmployeeAsset::route('/{record}/edit'),
        ];
    }
}
