<?php

namespace App\Filament\Resources\TaxRegimeConfigResource\Pages;

use App\Filament\Resources\TaxRegimeConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxRegimeConfigs extends ListRecords
{
    protected static string $resource = TaxRegimeConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
