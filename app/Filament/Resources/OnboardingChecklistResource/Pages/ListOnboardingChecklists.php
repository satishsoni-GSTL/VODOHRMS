<?php

namespace App\Filament\Resources\OnboardingChecklistResource\Pages;

use App\Filament\Resources\OnboardingChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnboardingChecklists extends ListRecords
{
    protected static string $resource = OnboardingChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
