<?php

namespace App\Filament\Resources\TaxRegimeSlabResource\Pages;

use App\Filament\Resources\TaxRegimeSlabResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxRegimeSlabs extends ListRecords
{
    protected static string $resource = TaxRegimeSlabResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
