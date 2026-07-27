<?php

namespace App\Filament\Resources\FinancialYearResource\Pages;

use App\Filament\Resources\FinancialYearResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinancialYear extends EditRecord
{
    protected static string $resource = FinancialYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
