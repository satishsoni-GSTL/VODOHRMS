<?php

namespace App\Filament\Resources\LeaveApplicationResource\Pages;

use App\Filament\Resources\LeaveApplicationResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewLeaveApplication extends ViewRecord
{
    protected static string $resource = LeaveApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return LeaveApplicationResource::approvalHeaderActions();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('employee.full_name')->label('Employee'),
            TextEntry::make('leaveType.name')->label('Leave Type'),
            TextEntry::make('from_date')->date(),
            TextEntry::make('to_date')->date(),
            TextEntry::make('days'),
            TextEntry::make('reason')->columnSpanFull(),
            TextEntry::make('status')->badge(),
        ])->columns(2);
    }
}
