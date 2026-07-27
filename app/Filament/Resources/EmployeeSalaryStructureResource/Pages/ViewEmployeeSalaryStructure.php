<?php

namespace App\Filament\Resources\EmployeeSalaryStructureResource\Pages;

use App\Filament\Resources\EmployeeSalaryStructureResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeSalaryStructure extends ViewRecord
{
    protected static string $resource = EmployeeSalaryStructureResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('employee.full_name')->label('Employee'),
            TextEntry::make('effective_from')->date(),
            TextEntry::make('effective_to')->date()->placeholder('Current'),
            TextEntry::make('annual_ctc')->money('INR'),
            TextEntry::make('monthly_gross')->money('INR'),
            TextEntry::make('increment_percent')->suffix('%')->placeholder('—'),
            RepeatableEntry::make('lines')
                ->schema([
                    TextEntry::make('component.name')->label('Component'),
                    TextEntry::make('component.type')->badge(),
                    TextEntry::make('monthly_amount')->money('INR'),
                    TextEntry::make('annual_amount')->money('INR'),
                ])
                ->columns(4)
                ->columnSpanFull(),
        ])->columns(3);
    }
}
