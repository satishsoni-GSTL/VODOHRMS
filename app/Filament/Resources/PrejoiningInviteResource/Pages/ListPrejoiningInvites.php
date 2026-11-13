<?php

namespace App\Filament\Resources\PrejoiningInviteResource\Pages;

use App\Filament\Resources\PrejoiningInviteResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListPrejoiningInvites extends ListRecords
{
    protected static string $resource = PrejoiningInviteResource::class;

    protected function getHeaderActions(): array
    {
        return [PrejoiningInviteResource::inviteAction(Action::class, pickEmployee: true)];
    }
}
