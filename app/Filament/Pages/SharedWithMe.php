<?php

namespace App\Filament\Pages;

use App\Models\SharedFileRecipient;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Files other people shared with the signed-in user's email. Opening one goes to the share
 * domain, where the same email-code check applies as for external recipients.
 */
class SharedWithMe extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'File Sharing';

    protected static ?string $navigationLabel = 'Shared With Me';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.shared-with-me';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => SharedFileRecipient::query()
                ->with('sharedFile.owner')
                ->where('email', strtolower((string) auth()->user()->email))
                ->whereNull('revoked_at')
                ->whereHas('sharedFile', fn (Builder $q) => $q->where('is_active', true)
                    ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('sharedFile.title')->label('File')->searchable()
                    ->description(fn (SharedFileRecipient $record) => $record->sharedFile->file_name.' · '.$record->sharedFile->humanSize()),
                Tables\Columns\TextColumn::make('sharedFile.owner.name')->label('Shared by'),
                Tables\Columns\TextColumn::make('created_at')->label('Shared on')->dateTime('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('sharedFile.expires_at')->label('Expires')->dateTime('d M Y')->placeholder('Never'),
                Tables\Columns\TextColumn::make('last_accessed_at')->label('Last opened')->since()->placeholder('Not yet'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SharedFileRecipient $record) => $record->shareUrl())
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Nothing shared with you yet')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
