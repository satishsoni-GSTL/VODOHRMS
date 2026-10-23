<?php

namespace App\Filament\Resources\SharedFileResource\Pages;

use App\Filament\Resources\SharedFileResource;
use App\Services\FileShareService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSharedFile extends CreateRecord
{
    protected static string $resource = SharedFileResource::class;

    protected static ?string $title = 'Share a file';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $service = app(FileShareService::class);

        $service->syncFileMeta($this->record);
        $sent = $service->inviteUninvited($this->record, auth()->user());

        Notification::make()
            ->title("Invitation sent to {$sent} ".str('person')->plural($sent))
            ->success()
            ->send();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
