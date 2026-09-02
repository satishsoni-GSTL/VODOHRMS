<?php

namespace App\Filament\Resources\AttendanceRegularizationResource\Pages;

use App\Filament\Resources\AttendanceRegularizationResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceRegularization extends ViewRecord
{
    protected static string $resource = AttendanceRegularizationResource::class;

    protected function getHeaderActions(): array
    {
        return AttendanceRegularizationResource::approvalHeaderActions();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('employee.full_name')->label('Employee'),
            TextEntry::make('attendance_date')->date(),
            TextEntry::make('request_type'),
            TextEntry::make('old_values')->label('Original Values')->formatStateUsing(fn ($state) => json_encode($state)),
            TextEntry::make('requested_values')->label('Requested Values')->formatStateUsing(fn ($state) => json_encode($state)),
            TextEntry::make('reason')->columnSpanFull(),
            TextEntry::make('status')->badge(),
        ])->columns(2);
    }
}
