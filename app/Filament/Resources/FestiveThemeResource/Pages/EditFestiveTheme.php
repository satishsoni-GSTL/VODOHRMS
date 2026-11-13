<?php

namespace App\Filament\Resources\FestiveThemeResource\Pages;

use App\Filament\Resources\FestiveThemeResource;
use Filament\Resources\Pages\EditRecord;

class EditFestiveTheme extends EditRecord
{
    protected static string $resource = FestiveThemeResource::class;

    public function getTitle(): string
    {
        return ($this->record->definition()['name'] ?? 'Theme').' theme';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
