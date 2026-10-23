<?php

namespace App\Filament\Resources\SharedFileResource\Pages;

use App\Filament\Resources\SharedFileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSharedFiles extends ListRecords
{
    protected static string $resource = SharedFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Share a file')->icon('heroicon-o-arrow-up-tray'),
        ];
    }
}
