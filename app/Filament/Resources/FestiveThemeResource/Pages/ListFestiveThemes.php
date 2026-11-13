<?php

namespace App\Filament\Resources\FestiveThemeResource\Pages;

use App\Filament\Resources\FestiveThemeResource;
use App\Support\FestiveThemes;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListFestiveThemes extends ListRecords
{
    protected static string $resource = FestiveThemeResource::class;

    protected ?string $subheading = 'Switch on a theme to dress up the login page and dashboard. Only one company-wide theme shows at a time (the one whose dates started most recently); Birthday is personal and shows to each employee on their birthday.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('endPreview')
                ->label('End preview')
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                ->visible(fn () => FestiveThemes::current()['preview'] ?? false)
                ->url(FestiveThemeResource::getUrl().'?festive_preview=off'),
        ];
    }
}
