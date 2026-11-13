<?php

namespace App\Filament\Resources\AppraisalResource\Pages;

use App\Filament\Resources\AppraisalResource;
use App\Models\Appraisal;
use App\Models\Employee;
use App\Services\AppraisalService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditAppraisal extends EditRecord
{
    protected static string $resource = AppraisalResource::class;

    public function getTitle(): string
    {
        return 'Appraisal · '.trim(preg_replace('/\s+/', ' ', (string) $this->record->employee?->full_name));
    }

    private function svc(): AppraisalService
    {
        return app(AppraisalService::class);
    }

    private function canEditAnything(): bool
    {
        $user = auth()->user();
        $svc = $this->svc();

        return $svc->canEditGoals($this->record, $user)
            || $svc->canSelfReview($this->record, $user)
            || $svc->canManagerReview($this->record, $user);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['goals'] = $this->record->goals()->get()
            ->map(fn ($g) => $g->only(['id', 'title', 'description', 'target', 'weightage', 'self_rating', 'self_comments', 'manager_rating', 'manager_comments']))
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Appraisal $record */
        return $this->svc()->save($record, $data, auth()->user());
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Saved';
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    protected function getFormActions(): array
    {
        return $this->canEditAnything()
            ? [$this->getSaveFormAction()->label('Save draft'), $this->getCancelFormAction()->label('Back')]
            : [$this->getCancelFormAction()->label('Back')];
    }

    /** Save the form first (if the user may edit), then run a workflow step. */
    private function step(callable $callback, string $success): void
    {
        try {
            if ($this->canEditAnything()) {
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
            }

            $callback($this->record->fresh());
            Notification::make()->title($success)->success()->send();
            $this->redirect(AppraisalResource::getUrl('edit', ['record' => $this->record]));
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $svc = $this->svc();
        $record = fn () => $this->record;

        return [
            Action::make('submitGoals')
                ->label('Submit Goals')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $svc->canEditGoals($record(), $user) && $record()->status === Appraisal::STATUS_GOALS_DRAFT)
                ->requiresConfirmation()
                ->modalDescription('Your goals go to your manager for approval. You can\'t change them unless they are returned.')
                ->action(fn () => $this->step(fn (Appraisal $a) => $svc->submitGoals($a, $user), 'Goals submitted to your manager')),
            Action::make('approveGoals')
                ->label('Approve Goals')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $svc->canApproveGoals($record(), $user))
                ->requiresConfirmation()
                ->action(fn () => $this->step(fn (Appraisal $a) => $svc->approveGoals($a, $user), 'Goals approved')),
            Action::make('returnGoals')
                ->label('Return Goals')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $svc->canApproveGoals($record(), $user))
                ->form([Forms\Components\Textarea::make('remarks')->label('What needs to change')->required()])
                ->action(fn (array $data) => $this->step(fn (Appraisal $a) => $svc->returnGoals($a, $user, $data['remarks']), 'Goals returned to the employee')),
            Action::make('submitSelf')
                ->label('Submit Self Review')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $svc->canSelfReview($record(), $user))
                ->requiresConfirmation()
                ->modalDescription('Your ratings go to your manager. You can\'t change them afterwards.')
                ->action(fn () => $this->step(fn (Appraisal $a) => $svc->submitSelfReview($a, $user), 'Self review submitted')),
            Action::make('submitManager')
                ->label('Submit Review')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $svc->canManagerReview($record(), $user))
                ->requiresConfirmation()
                ->modalDescription('The employee will see your ratings and the overall score.')
                ->action(fn () => $this->step(fn (Appraisal $a) => $svc->submitManagerReview($a, $user), 'Review submitted')),
            Action::make('calibrate')
                ->label('Calibrate Rating')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn () => $svc->canCalibrate($record(), $user))
                ->fillForm(fn () => ['final_rating' => $record()->final_rating, 'final_remarks' => $record()->final_remarks])
                ->form([
                    Forms\Components\TextInput::make('final_rating')->label('Final rating (1-5)')->numeric()->minValue(1)->maxValue(5)->step(0.01)->required(),
                    Forms\Components\Textarea::make('final_remarks')->label('Reason')->required(),
                ])
                ->action(fn (array $data) => $this->step(
                    fn (Appraisal $a) => $svc->calibrate($a, $user, (float) $data['final_rating'], $data['final_remarks']),
                    'Final rating updated',
                )),
            Action::make('reassign')
                ->label('Change Reviewer')
                ->icon('heroicon-o-user-circle')
                ->color('gray')
                ->visible(fn () => $svc->isHr($user) && $record()->status !== Appraisal::STATUS_CLOSED)
                ->fillForm(fn () => ['manager_id' => $record()->manager_id])
                ->form([
                    Forms\Components\Select::make('manager_id')->label('Reviewer')
                        ->options(fn () => Employee::query()->whereKeyNot($record()->employee_id)
                            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD])
                            ->orderBy('first_name')->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->employee_code} - {$e->full_name}"]))
                        ->searchable(),
                ])
                ->action(fn (array $data) => $this->step(fn (Appraisal $a) => $svc->reassignManager($a, $data['manager_id'] ?: null), 'Reviewer changed')),
        ];
    }
}
