<?php

namespace App\Filament\Resources\OnboardingTaskResource\Pages;

use App\Filament\Resources\OnboardingTaskResource;
use App\Filament\Resources\OnboardingTaskTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnboardingTasks extends ListRecords
{
    protected static string $resource = OnboardingTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('templates')
                ->label('Task Templates')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->visible(fn () => auth()->user()->can('onboarding.manage'))
                ->url(OnboardingTaskTemplateResource::getUrl()),
            Actions\CreateAction::make()->label('Add Task'),
        ];
    }
}
