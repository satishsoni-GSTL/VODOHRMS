<?php

namespace App\Filament\Resources\WorkFromHomeRequestResource\Pages;

use App\Filament\Resources\WorkFromHomeRequestResource;
use App\Models\WorkFromHomeRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkFromHomeRequest extends ViewRecord
{
    protected static string $resource = WorkFromHomeRequestResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('employee.full_name')->label('Employee'),
            TextEntry::make('from_date')->date(),
            TextEntry::make('to_date')->date(),
            TextEntry::make('total_days')->label('Working Days')->state(fn (WorkFromHomeRequest $record) => $record->total_days),
            TextEntry::make('reason')->columnSpanFull(),
            TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => WorkFromHomeRequest::STATUSES[$state] ?? $state),
        ])->columns(2);
    }
}
