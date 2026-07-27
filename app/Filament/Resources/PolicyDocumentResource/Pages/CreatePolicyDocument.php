<?php

namespace App\Filament\Resources\PolicyDocumentResource\Pages;

use App\Filament\Resources\PolicyDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePolicyDocument extends CreateRecord
{
    protected static string $resource = PolicyDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = auth()->id();

        return $data;
    }
}
