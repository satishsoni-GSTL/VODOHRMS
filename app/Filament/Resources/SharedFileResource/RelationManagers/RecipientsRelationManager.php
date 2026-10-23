<?php

namespace App\Filament\Resources\SharedFileResource\RelationManagers;

use App\Models\SharedFileRecipient;
use App\Models\User;
use App\Services\FileShareService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'People with access';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Internal user (optional)')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::query()
                        ->where('is_active', true)
                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email})"])
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($user = User::find($state)) {
                            $set('email', $user->email);
                            $set('name', $user->name);
                        }
                    })
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('shared_file_id', $this->getOwnerRecord()->id))
                    ->validationMessages(['unique' => 'This person already has access to this file.']),
                Forms\Components\TextInput::make('name')->maxLength(255),
                Forms\Components\Toggle::make('can_comment')->label('Can comment')->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()
                    ->description(fn (SharedFileRecipient $record) => $record->name),
                Tables\Columns\TextColumn::make('type')
                    ->state(fn (SharedFileRecipient $record) => $record->isInternal() ? 'Internal' : 'External')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Internal' ? 'info' : 'warning'),
                Tables\Columns\IconColumn::make('can_comment')->label('Comment')->boolean(),
                Tables\Columns\TextColumn::make('invited_at')->label('Invited')->dateTime('d M Y, h:i A')->placeholder('—'),
                Tables\Columns\TextColumn::make('last_accessed_at')->label('Last opened')->since()->placeholder('Not yet'),
                Tables\Columns\TextColumn::make('access_count')->label('Views'),
                Tables\Columns\TextColumn::make('status')
                    ->state(fn (SharedFileRecipient $record) => $record->isRevoked() ? 'Revoked' : 'Active')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Active' ? 'success' : 'danger'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add person')
                    ->modalHeading('Share with another person')
                    ->after(fn (SharedFileRecipient $record) => app(FileShareService::class)->invite($record, auth()->user()))
                    ->successNotificationTitle('Invitation sent'),
            ])
            ->actions([
                Tables\Actions\Action::make('resend')
                    ->label('Resend invite')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (SharedFileRecipient $record) => ! $record->isRevoked())
                    ->requiresConfirmation()
                    ->action(function (SharedFileRecipient $record) {
                        app(FileShareService::class)->invite($record, auth()->user());
                        Notification::make()->title('Invitation re-sent to '.$record->email)->success()->send();
                    }),
                Tables\Actions\Action::make('copyLink')
                    ->label('Copy link')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->visible(fn (SharedFileRecipient $record) => ! $record->isRevoked())
                    ->modalHeading('Share link')
                    ->modalDescription('This link only works for this person — opening it requires a code sent to their email.')
                    ->form(fn (SharedFileRecipient $record) => [
                        Forms\Components\TextInput::make('link')->default($record->shareUrl())->readOnly(),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\EditAction::make()->form([
                    Forms\Components\TextInput::make('name')->maxLength(255),
                    Forms\Components\Toggle::make('can_comment')->label('Can comment'),
                ]),
                Tables\Actions\Action::make('revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (SharedFileRecipient $record) => ! $record->isRevoked())
                    ->requiresConfirmation()
                    ->modalDescription('Their link stops working immediately. Their past comments stay visible.')
                    ->action(fn (SharedFileRecipient $record) => app(FileShareService::class)->revoke($record, auth()->user())),
                Tables\Actions\Action::make('restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (SharedFileRecipient $record) => $record->isRevoked())
                    ->requiresConfirmation()
                    ->action(fn (SharedFileRecipient $record) => app(FileShareService::class)->restore($record, auth()->user())),
            ]);
    }
}
