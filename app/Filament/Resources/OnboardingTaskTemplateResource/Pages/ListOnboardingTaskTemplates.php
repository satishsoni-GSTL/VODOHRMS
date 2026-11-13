<?php

namespace App\Filament\Resources\OnboardingTaskTemplateResource\Pages;

use App\Filament\Resources\OnboardingTaskTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnboardingTaskTemplates extends ListRecords
{
    protected static string $resource = OnboardingTaskTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
