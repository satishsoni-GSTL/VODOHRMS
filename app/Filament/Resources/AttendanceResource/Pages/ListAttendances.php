<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Bulk Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(fn () => static::getResource()::getUrl('import'))
                ->visible(fn () => auth()->user()->can('attendance.import'))
                ->color('gray'),
            Actions\CreateAction::make(),
        ];
    }
}
