<?php

namespace App\Filament\Resources\FullFinalSettlementResource\Pages;

use App\Filament\Resources\FullFinalSettlementResource;
use App\Models\FullFinalSettlement;
use Filament\Resources\Pages\EditRecord;

class EditFullFinalSettlement extends EditRecord
{
    protected static string $resource = FullFinalSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate($record, array $data): FullFinalSettlement
    {
        $record->fill($data);
        $record->recalculateFinalAmount();
        $record->save();

        return $record;
    }
}
