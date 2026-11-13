<?php

namespace App\Filament\Resources\OnboardingTaskTemplateResource\Pages;

use App\Filament\Resources\OnboardingTaskTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOnboardingTaskTemplate extends EditRecord
{
    protected static string $resource = OnboardingTaskTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
