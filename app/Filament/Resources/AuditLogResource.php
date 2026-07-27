<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Roles & Permissions';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('old_values')
                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                    ->disabled()
                    ->rows(10)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('new_values')
                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                    ->disabled()
                    ->rows(10)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('When')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('User')->placeholder('System'),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'create', 'approve', 'approved' => 'success',
                        'reject', 'rejected', 'delete' => 'danger',
                        'send_back', 'sent_back', 'reopen' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('module')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Record Type')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                Tables\Columns\TextColumn::make('auditable_id')->label('Record ID'),
                Tables\Columns\TextColumn::make('ip_address'),
                Tables\Columns\TextColumn::make('reason')->limit(40)->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')->options([
                    'create' => 'Create',
                    'update' => 'Update',
                    'delete' => 'Delete',
                    'approve' => 'Approve',
                    'reject' => 'Reject',
                    'send_back' => 'Send Back',
                    'finalize' => 'Finalize',
                    'lock' => 'Lock',
                    'reopen' => 'Reopen',
                ]),
                Tables\Filters\SelectFilter::make('module')->options(fn () => AuditLog::query()
                    ->distinct()
                    ->orderBy('module')
                    ->pluck('module', 'module')
                    ->filter()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
