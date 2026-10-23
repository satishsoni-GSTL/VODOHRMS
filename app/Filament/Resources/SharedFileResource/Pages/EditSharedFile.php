<?php

namespace App\Filament\Resources\SharedFileResource\Pages;

use App\Filament\Resources\SharedFileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSharedFile extends EditRecord
{
    protected static string $resource = SharedFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
