<?php

namespace App\Filament\Resources\TaxSectionResource\Pages;

use App\Filament\Resources\TaxSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxSections extends ListRecords
{
    protected static string $resource = TaxSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
