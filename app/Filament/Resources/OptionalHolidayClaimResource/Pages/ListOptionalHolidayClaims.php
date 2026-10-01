<?php

namespace App\Filament\Resources\OptionalHolidayClaimResource\Pages;

use App\Filament\Resources\OptionalHolidayClaimResource;
use App\Services\OptionalHolidayService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOptionalHolidayClaims extends ListRecords
{
    protected static string $resource = OptionalHolidayClaimResource::class;

    public function getSubheading(): ?string
    {
        $employee = auth()->user()->employee;

        if (! $employee) {
            return null;
        }

        $service = app(OptionalHolidayService::class);
        $year = (int) now()->format('Y');

        return "You have used {$service->usedFor($employee, $year)} of {$service->limitFor($employee, $year)} optional holidays in {$year}.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Claim Optional Holiday'),
        ];
    }
}
