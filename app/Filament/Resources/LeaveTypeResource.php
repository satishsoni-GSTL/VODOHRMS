<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveTypeResource\Pages;
use App\Models\LeaveType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeaveTypeResource extends Resource
{
    protected static ?string $model = LeaveType::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Leave';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('annual_entitlement')->numeric()->default(0)->required(),
                        Forms\Components\Select::make('accrual_frequency')
                            ->options(['annual' => 'Annual', 'monthly' => 'Monthly', 'none' => 'None'])
                            ->default('annual')
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Rules')
                    ->schema([
                        Forms\Components\Toggle::make('carry_forward_allowed')->default(false)->live(),
                        Forms\Components\TextInput::make('max_carry_forward')->numeric()
                            ->visible(fn (Forms\Get $get) => $get('carry_forward_allowed')),
                        Forms\Components\Toggle::make('encashment_allowed')->default(false),
                        Forms\Components\Toggle::make('allow_negative_balance')->default(false),
                        Forms\Components\Toggle::make('half_day_allowed')->default(true),
                        Forms\Components\Toggle::make('sandwich_rule_applicable')->default(false),
                        Forms\Components\Toggle::make('probation_allowed')->default(true),
                        Forms\Components\Toggle::make('attachment_required')->default(false),
                        Forms\Components\TextInput::make('min_days_per_request')->numeric(),
                        Forms\Components\TextInput::make('max_days_per_request')->numeric(),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('annual_entitlement')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('accrual_frequency'),
                Tables\Columns\IconColumn::make('carry_forward_allowed')->boolean()->toggleable(),
                Tables\Columns\IconColumn::make('encashment_allowed')->boolean()->toggleable(),
                Tables\Columns\IconColumn::make('half_day_allowed')->boolean()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index' => Pages\ListLeaveTypes::route('/'),
            'create' => Pages\CreateLeaveType::route('/create'),
            'edit' => Pages\EditLeaveType::route('/{record}/edit'),
        ];
    }
}
