<?php

namespace App\Filament\Resources\TaxSectionResource\Pages;

use App\Filament\Resources\TaxSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxSection extends EditRecord
{
    protected static string $resource = TaxSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
