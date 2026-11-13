<?php

namespace App\Filament\Resources\TravelRequestResource\Pages;

use App\Filament\Resources\TravelRequestResource;
use App\Models\TravelRequest;
use Filament\Actions\Action;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewTravelRequest extends ViewRecord
{
    protected static string $resource = TravelRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...TravelRequestResource::approvalHeaderActions(),
            TravelRequestResource::payAdvanceAction(Action::class),
            TravelRequestResource::closeAction(Action::class),
            TravelRequestResource::cancelAction(Action::class),
            TravelRequestResource::reapplyAction(Action::class),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Trip')->schema([
                TextEntry::make('request_number')->label('Request No.'),
                TextEntry::make('employee.full_name')->label('Employee'),
                TextEntry::make('route_label')->label('Route'),
                TextEntry::make('travel_type')->formatStateUsing(fn ($state) => TravelRequest::TYPES[$state] ?? $state),
                TextEntry::make('departure_date')->date(),
                TextEntry::make('return_date')->date(),
                TextEntry::make('days')->label('Days'),
                TextEntry::make('travel_mode')->label('Mode')->formatStateUsing(fn ($state) => TravelRequest::MODES[$state] ?? $state),
                TextEntry::make('accommodation_required')->label('Accommodation')->formatStateUsing(fn ($state) => $state ? 'Required' : 'Not required'),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => TravelRequest::STATUSES[$state] ?? $state),
                TextEntry::make('purpose')->columnSpanFull(),
            ])->columns(3),
            Section::make('Money')->schema([
                TextEntry::make('estimated_cost')->money('INR'),
                TextEntry::make('advance_requested')->money('INR'),
                TextEntry::make('advance_paid')->money('INR'),
                TextEntry::make('advance_paid_on')->date()->placeholder('—'),
                TextEntry::make('advance_reference')->label('Reference')->placeholder('—'),
                TextEntry::make('outstanding')->label('Advance not yet adjusted')
                    ->state(fn (TravelRequest $record) => '₹'.number_format($record->outstandingAdvance(), 2)),
                TextEntry::make('claims')->label('Linked expense claims')
                    ->state(fn (TravelRequest $record) => $record->expenseClaims()->get()
                        ->map(fn ($c) => "{$c->claim_number} (₹".number_format((float) $c->total_approved_amount ?: (float) $c->total_requested_amount, 2).", {$c->status})")
                        ->implode(', ') ?: '—')
                    ->columnSpanFull(),
                TextEntry::make('closure_remarks')->placeholder('—')->columnSpanFull(),
            ])->columns(3),
        ]);
    }
}
