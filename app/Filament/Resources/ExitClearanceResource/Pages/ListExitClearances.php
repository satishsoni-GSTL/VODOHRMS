<?php

namespace App\Filament\Resources\ExitClearanceResource\Pages;

use App\Filament\Resources\ExitClearanceResource;
use Filament\Resources\Pages\ListRecords;

class ListExitClearances extends ListRecords
{
    protected static string $resource = ExitClearanceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
