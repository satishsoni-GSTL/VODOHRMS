<?php

namespace App\Filament\Resources\TaxRegimeConfigResource\Pages;

use App\Filament\Resources\TaxRegimeConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxRegimeConfig extends EditRecord
{
    protected static string $resource = TaxRegimeConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
