<?php

namespace App\Filament\Resources\FullFinalSettlementResource\Pages;

use App\Filament\Resources\FullFinalSettlementResource;
use App\Models\Resignation;
use App\Services\FnFSettlementService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFullFinalSettlements extends ListRecords
{
    protected static string $resource = FullFinalSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calculate')
                ->label('Calculate F&F')
                ->icon('heroicon-o-calculator')
                ->visible(fn () => auth()->user()->can('fnf.process'))
                ->form([
                    Forms\Components\Select::make('resignation_id')
                        ->label('Resignation')
                        ->options(fn () => Resignation::query()
                            ->where('status', Resignation::STATUS_HR_APPROVED)
                            ->with('employee')
                            ->get()
                            ->mapWithKeys(fn ($r) => [$r->id => "{$r->employee->employee_code} - {$r->employee->full_name}"]))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $resignation = Resignation::findOrFail($data['resignation_id']);
                    app(FnFSettlementService::class)->calculate($resignation, auth()->user());
                    Notification::make()->title('F&F settlement calculated')->success()->send();
                }),
        ];
    }
}
