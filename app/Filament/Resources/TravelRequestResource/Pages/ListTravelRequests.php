<?php

namespace App\Filament\Resources\TravelRequestResource\Pages;

use App\Filament\Resources\TravelRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTravelRequests extends ListRecords
{
    protected static string $resource = TravelRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New Travel Request')];
    }
}
