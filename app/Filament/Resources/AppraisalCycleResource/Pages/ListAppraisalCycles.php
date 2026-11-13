<?php

namespace App\Filament\Resources\AppraisalCycleResource\Pages;

use App\Filament\Resources\AppraisalCycleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppraisalCycles extends ListRecords
{
    protected static string $resource = AppraisalCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New Cycle')];
    }
}
