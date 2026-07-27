<?php

namespace App\Filament\Resources\PolicyDocumentResource\Pages;

use App\Filament\Resources\PolicyDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPolicyDocument extends EditRecord
{
    protected static string $resource = PolicyDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
