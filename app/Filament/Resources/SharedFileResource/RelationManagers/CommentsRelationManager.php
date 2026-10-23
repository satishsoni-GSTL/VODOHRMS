<?php

namespace App\Filament\Resources\SharedFileResource\RelationManagers;

use App\Models\SharedFileComment;
use App\Services\FileShareService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The owner's side of the conversation. Rows are ordered thread-by-thread (each root comment
 * followed by its replies) so the table reads like the share page.
 */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comments';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('parent')
                ->orderByRaw('COALESCE(parent_id, id) DESC')
                ->orderBy('id'))
            ->columns([
                Tables\Columns\TextColumn::make('author_name')
                    ->label('From')
                    ->formatStateUsing(fn (SharedFileComment $record, string $state) => $record->parent_id ? '↳ '.$state : $state)
                    ->description(fn (SharedFileComment $record) => $record->author_email)
                    ->weight(fn (SharedFileComment $record) => $record->parent_id ? null : 'bold'),
                Tables\Columns\TextColumn::make('body')
                    ->label('Comment')
                    ->wrap()
                    ->description(fn (SharedFileComment $record) => $record->parent
                        ? 'Reply to '.$record->parent->author_name.': "'.Str::limit($record->parent->body, 60).'"'
                        : null),
                Tables\Columns\TextColumn::make('created_at')->label('Posted')->since()
                    ->tooltip(fn (SharedFileComment $record) => $record->created_at->format('d M Y, h:i A')),
            ])
            ->paginated([25, 50, 100])
            ->headerActions([
                Tables\Actions\Action::make('comment')
                    ->label('Add comment')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->modalDescription('Everyone with active access is emailed about a new comment.')
                    ->form([
                        Forms\Components\Textarea::make('body')->label('Comment')->required()->maxLength(5000)->rows(4),
                    ])
                    ->action(function (array $data) {
                        app(FileShareService::class)->addComment($this->getOwnerRecord(), $data['body'], user: auth()->user());
                        Notification::make()->title('Comment posted')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reply')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->modalHeading(fn (SharedFileComment $record) => 'Reply to '.$record->author_name)
                    ->modalDescription(fn (SharedFileComment $record) => Str::limit($record->body, 200))
                    ->form([
                        Forms\Components\Textarea::make('body')->label('Reply')->required()->maxLength(5000)->rows(4),
                    ])
                    ->action(function (SharedFileComment $record, array $data) {
                        app(FileShareService::class)->addComment($this->getOwnerRecord(), $data['body'], $record, user: auth()->user());
                        Notification::make()->title('Reply posted')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('Replies to this comment are deleted too.'),
            ]);
    }
}
