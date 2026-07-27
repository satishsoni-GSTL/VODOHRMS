<?php

namespace App\Filament\Resources\PayrollInputResource\Pages;

use App\Filament\Resources\PayrollInputResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollInputs extends ListRecords
{
    protected static string $resource = PayrollInputResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
