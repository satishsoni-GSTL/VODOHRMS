<?php

namespace App\Filament\Resources\PolicyDocumentResource\Pages;

use App\Filament\Resources\PolicyDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPolicyDocuments extends ListRecords
{
    protected static string $resource = PolicyDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
