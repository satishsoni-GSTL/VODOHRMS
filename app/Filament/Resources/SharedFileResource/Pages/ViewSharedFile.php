<?php

namespace App\Filament\Resources\SharedFileResource\Pages;

use App\Filament\Resources\SharedFileResource;
use App\Models\SharedFile;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSharedFile extends ViewRecord
{
    protected static string $resource = SharedFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (SharedFile $record) => route('shared-files.download', $record))
                ->openUrlInNewTab(),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
