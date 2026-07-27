<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Bulk Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(fn () => static::getResource()::getUrl('import'))
                ->visible(fn () => auth()->user()->can('employee.import'))
                ->color('gray'),
            Actions\CreateAction::make(),
        ];
    }
}
