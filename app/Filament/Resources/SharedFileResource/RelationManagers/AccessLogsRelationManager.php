<?php

namespace App\Filament\Resources\SharedFileResource\RelationManagers;

use App\Models\SharedFileAccessLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AccessLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'accessLogs';

    protected static ?string $title = 'Activity';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['recipient', 'user']))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime('d M Y, h:i A'),
                Tables\Columns\TextColumn::make('action')
                    ->formatStateUsing(fn (string $state) => SharedFileAccessLog::ACTIONS[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'code_failed', 'revoked' => 'danger',
                        'downloaded', 'verified' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('who')
                    ->state(fn (SharedFileAccessLog $record) => $record->recipient?->email ?? $record->user?->name ?? '—'),
                Tables\Columns\TextColumn::make('ip_address')->label('IP')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')->options(SharedFileAccessLog::ACTIONS),
            ]);
    }
}
