<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SharedFileResource\Pages;
use App\Filament\Resources\SharedFileResource\RelationManagers;
use App\Models\SharedFile;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SharedFileResource extends Resource
{
    protected static ?string $model = SharedFile::class;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'File Sharing';

    protected static ?string $navigationLabel = 'My Shared Files';

    protected static ?string $modelLabel = 'shared file';

    protected static ?int $navigationSort = 1;

    /** Owners see their own files; fileshare.manage sees everyone's. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user?->can('fileshare.manage') ? $query : $query->where('owner_id', $user?->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('File')
                    ->schema([
                        Forms\Components\TextInput::make('title')->required()->maxLength(255),
                        Forms\Components\FileUpload::make('file_path')
                            ->label('File')
                            ->disk(config('fileshare.disk'))
                            ->directory(config('fileshare.directory').'/'.now()->format('Y/m'))
                            ->visibility('private')
                            ->storeFileNamesIn('file_name')
                            ->maxSize(config('fileshare.max_upload_kb'))
                            ->required()
                            ->hiddenOn('edit'),
                        Forms\Components\Textarea::make('description')
                            ->label('Message to recipients')
                            ->maxLength(2000)
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Link expires on')
                            ->helperText('Leave empty to keep the links working until you disable them.')
                            ->minDate(now())
                            ->seconds(false),
                        Forms\Components\Toggle::make('allow_download')
                            ->label('Allow recipients to download')
                            ->helperText('When off, PDFs, images, audio/video and text can still be viewed in the browser.')
                            ->default(true),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Sharing enabled')
                            ->helperText('Turn off to block every link to this file at once.')
                            ->default(true)
                            ->hiddenOn('create'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Share with')
                    ->description('Each person gets a private link on '.(config('fileshare.domain') ?: 'the share site').' and must confirm a code sent to their email before the file opens.')
                    ->schema([
                        Forms\Components\Repeater::make('recipients')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Internal user (optional)')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search) => User::query()
                                        ->where('is_active', true)
                                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%")
                                            ->orWhere('employee_code', 'like', "%{$search}%"))
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
                                    }),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->distinct()
                                    ->maxLength(255)
                                    ->disabled(fn (Get $get) => filled($get('user_id')))
                                    ->dehydrated(),
                                Forms\Components\TextInput::make('name')->maxLength(255),
                                Forms\Components\Toggle::make('can_comment')->label('Can comment')->default(true)->inline(false),
                            ])
                            ->columns(4)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add person')
                            ->reorderable(false),
                    ])
                    ->hiddenOn('edit'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\TextEntry::make('title'),
                        Infolists\Components\TextEntry::make('file_name')->label('File'),
                        Infolists\Components\TextEntry::make('size')->label('Size')->formatStateUsing(fn (SharedFile $record) => $record->humanSize()),
                        Infolists\Components\TextEntry::make('owner.name')->label('Shared by'),
                        Infolists\Components\TextEntry::make('created_at')->label('Shared on')->dateTime('d M Y, h:i A'),
                        Infolists\Components\TextEntry::make('expires_at')->label('Expires')->dateTime('d M Y, h:i A')->placeholder('Never'),
                        Infolists\Components\IconEntry::make('allow_download')->label('Download allowed')->boolean(),
                        Infolists\Components\TextEntry::make('status')
                            ->state(fn (SharedFile $record) => match (true) {
                                ! $record->is_active => 'Disabled',
                                $record->isExpired() => 'Expired',
                                default => 'Active',
                            })
                            ->badge()
                            ->color(fn (string $state) => $state === 'Active' ? 'success' : 'danger'),
                        Infolists\Components\TextEntry::make('description')->label('Message')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('owner')->withCount(['recipients', 'comments']))
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()
                    ->description(fn (SharedFile $record) => $record->file_name.' · '.$record->humanSize()),
                Tables\Columns\TextColumn::make('owner.name')->label('Shared by')
                    ->visible(fn () => auth()->user()?->can('fileshare.manage')),
                Tables\Columns\TextColumn::make('recipients_count')->label('People')->badge(),
                Tables\Columns\TextColumn::make('comments_count')->label('Comments')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->state(fn (SharedFile $record) => match (true) {
                        ! $record->is_active => 'Disabled',
                        $record->isExpired() => 'Expired',
                        default => 'Active',
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === 'Active' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('expires_at')->label('Expires')->dateTime('d M Y')->placeholder('Never')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Shared on')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Sharing enabled'),
                Tables\Filters\Filter::make('mine')
                    ->label('Only my files')
                    ->query(fn (Builder $query) => $query->where('owner_id', auth()->id()))
                    ->visible(fn () => auth()->user()?->can('fileshare.manage')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (SharedFile $record) => route('shared-files.download', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RecipientsRelationManager::class,
            RelationManagers\CommentsRelationManager::class,
            RelationManagers\AccessLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSharedFiles::route('/'),
            'create' => Pages\CreateSharedFile::route('/create'),
            'view' => Pages\ViewSharedFile::route('/{record}'),
            'edit' => Pages\EditSharedFile::route('/{record}/edit'),
        ];
    }
}
