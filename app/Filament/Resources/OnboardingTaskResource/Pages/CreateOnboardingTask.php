<?php

namespace App\Filament\Resources\OnboardingTaskResource\Pages;

use App\Filament\Resources\OnboardingTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOnboardingTask extends CreateRecord
{
    protected static string $resource = OnboardingTaskResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
