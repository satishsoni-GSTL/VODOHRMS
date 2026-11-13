<?php

namespace App\Filament\Resources\PositionResource\RelationManagers;

use App\Models\PositionAssignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Read-only history of who held this position; changes go through Assign / Vacate. */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Assignment History';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code'),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee'),
                Tables\Columns\TextColumn::make('start_date')->date(),
                Tables\Columns\TextColumn::make('end_date')->date()->placeholder('Current'),
                Tables\Columns\TextColumn::make('reason')->formatStateUsing(fn (?string $state) => PositionAssignment::REASONS[$state] ?? $state),
                Tables\Columns\TextColumn::make('remarks')->wrap(),
                Tables\Columns\TextColumn::make('creator.name')->label('By')->toggleable(),
            ])
            ->defaultSort('start_date', 'desc');
    }
}
