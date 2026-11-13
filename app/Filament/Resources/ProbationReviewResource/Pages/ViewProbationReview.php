<?php

namespace App\Filament\Resources\ProbationReviewResource\Pages;

use App\Filament\Resources\ProbationReviewResource;
use App\Models\ProbationReview;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewProbationReview extends ViewRecord
{
    protected static string $resource = ProbationReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ProbationReviewResource::approvalHeaderActions(),
            ProbationReviewResource::letterAction(Action::class),
            ProbationReviewResource::reapplyAction(Action::class),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('employee.full_name')->label('Employee'),
            TextEntry::make('employee.employee_code')->label('Code'),
            TextEntry::make('employee.date_of_joining')->label('Date of Joining')->date(),
            TextEntry::make('probation_end_date')->label('Probation Ends')->date(),
            TextEntry::make('recommendation_label')->label('Recommendation'),
            TextEntry::make('effective_date')
                ->label(fn (ProbationReview $record) => $record->recommendation === ProbationReview::RECOMMEND_CONFIRM ? 'Confirmed From' : 'New Probation End')
                ->date(),
            TextEntry::make('rating')->formatStateUsing(fn ($state) => ProbationReview::RATINGS[$state] ?? '—')->placeholder('—'),
            TextEntry::make('submitter.name')->label('Submitted By'),
            TextEntry::make('performance_summary')->label('Performance during probation')->columnSpanFull(),
            TextEntry::make('improvement_areas')->label('Areas for improvement')->placeholder('—')->columnSpanFull(),
            TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => ProbationReview::STATUSES[$state] ?? $state),
            TextEntry::make('decided_at')->label('Decided')->dateTime()->placeholder('—'),
        ])->columns(2);
    }
}
