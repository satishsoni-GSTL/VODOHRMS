<?php

namespace App\Filament\Resources\ProbationReviewResource\Pages;

use App\Filament\Pages\ProbationDue;
use App\Filament\Resources\ProbationReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProbationReviews extends ListRecords
{
    protected static string $resource = ProbationReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('due')
                ->label('Probation Due')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->url(ProbationDue::getUrl()),
            Actions\CreateAction::make()->label('New Review'),
        ];
    }
}
