<?php

namespace App\Filament\Resources\ResignationResource\Pages;

use App\Filament\Resources\ResignationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResignations extends ListRecords
{
    protected static string $resource = ResignationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
