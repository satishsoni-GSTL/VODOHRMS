<?php

namespace App\Filament\Resources\AppraisalCycleResource\Pages;

use App\Filament\Resources\AppraisalCycleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAppraisalCycle extends CreateRecord
{
    protected static string $resource = AppraisalCycleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
