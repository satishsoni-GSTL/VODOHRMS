<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeDocument;
use App\Services\EmployeeProfileService;
use App\Services\OnboardingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The employee's documents on the employee record: HR uploads, sees what the employee or the
 * pre-joining portal uploaded, verifies and removes. Identity documents, so it needs the
 * sensitive-data permission (or onboarding.manage) to see.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user && ($user->can('employee.view-sensitive') || $user->can('onboarding.manage'));
    }

    private static function canManage(): bool
    {
        $user = auth()->user();

        return $user && ($user->can('employee.edit-sensitive') || $user->can('onboarding.manage'));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('document_type')->options(EmployeeDocument::types())->required(),
            Forms\Components\FileUpload::make('file_path')
                ->label('File')
                ->disk('local')
                ->directory(fn () => 'employee-documents/'.$this->getOwnerRecord()->id)
                ->visibility('private')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(10240)
                ->storeFileNamesIn('file_name')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('file_name')
            ->columns([
                Tables\Columns\TextColumn::make('type_label')->label('Type'),
                Tables\Columns\TextColumn::make('file_name')->label('File')->limit(40)
                    ->url(fn (EmployeeDocument $record) => route('employee-documents.download', $record))
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('source')->badge()
                    ->formatStateUsing(fn (?string $state) => EmployeeDocument::SOURCES[$state] ?? $state),
                Tables\Columns\TextColumn::make('uploaded_at')->dateTime('d M Y H:i'),
                Tables\Columns\IconColumn::make('is_verified')->label('Verified')->boolean(),
                Tables\Columns\TextColumn::make('verifiedBy.name')->label('Verified by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('uploaded_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Upload Document')
                    ->visible(fn () => static::canManage())
                    ->mutateFormDataUsing(fn (array $data) => $data + [
                        'source' => 'hr',
                        'uploaded_by' => auth()->id(),
                        'uploaded_at' => now(),
                    ])
                    ->after(fn () => app(OnboardingService::class)->refresh($this->getOwnerRecord())),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label(fn (EmployeeDocument $record) => $record->is_verified ? 'Unverify' : 'Verify')
                    ->icon(fn (EmployeeDocument $record) => $record->is_verified ? 'heroicon-o-x-mark' : 'heroicon-o-check-badge')
                    ->color(fn (EmployeeDocument $record) => $record->is_verified ? 'gray' : 'success')
                    ->visible(fn () => static::canManage())
                    ->action(fn (EmployeeDocument $record) => $record->update($record->is_verified
                        ? ['is_verified' => false, 'verified_by' => null, 'verified_at' => null]
                        : ['is_verified' => true, 'verified_by' => auth()->id(), 'verified_at' => now()])),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => static::canManage())
                    ->after(function (EmployeeDocument $record) {
                        Storage::disk('local')->delete($record->file_path);
                        app(OnboardingService::class)->refresh($this->getOwnerRecord());
                    }),
            ]);
    }
}
