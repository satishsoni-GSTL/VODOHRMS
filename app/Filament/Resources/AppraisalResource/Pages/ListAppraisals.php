<?php

namespace App\Filament\Resources\AppraisalResource\Pages;

use App\Filament\Resources\AppraisalResource;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;

class ListAppraisals extends ListRecords
{
    protected static string $resource = AppraisalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Excel of the cycle's appraisals, scoped to what this user can see (HR: all; managers: their line).
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                // Reviewers and HR only — someone who can see just their own appraisal has nothing to export.
                ->visible(fn () => AppraisalResource::scoped(Appraisal::query(), auth()->user())
                    ->where('employee_id', '!=', auth()->user()->employee_id ?? 0)->exists())
                ->form([
                    Forms\Components\Select::make('cycle')
                        ->label('Cycle')
                        ->options(fn () => AppraisalCycle::query()->where('status', '!=', AppraisalCycle::STATUS_DRAFT)
                            ->orderByDesc('period_start')->pluck('name', 'id'))
                        ->default(fn () => AppraisalCycle::query()->where('status', '!=', AppraisalCycle::STATUS_DRAFT)->orderByDesc('period_start')->value('id'))
                        ->required(),
                ])
                ->action(fn (array $data) => $this->redirect(route('reports.download', ['type' => 'appraisal', 'cycle' => $data['cycle']]))),
        ];
    }
}
