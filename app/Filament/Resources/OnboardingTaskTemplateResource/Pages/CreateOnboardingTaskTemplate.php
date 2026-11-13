<?php

namespace App\Filament\Resources\OnboardingTaskTemplateResource\Pages;

use App\Filament\Resources\OnboardingTaskTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOnboardingTaskTemplate extends CreateRecord
{
    protected static string $resource = OnboardingTaskTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
