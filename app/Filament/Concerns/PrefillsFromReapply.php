<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Arr;

/**
 * For a resource's CreateRecord page: when opened as `?reapply={id}` (the "Reapply" action
 * on a rejected / sent-back request), pre-fill the form from that request. The source is
 * looked up through the resource's own scoped query, so users can only reapply from
 * requests they can see; saving goes through the normal create flow as a new request.
 */
trait PrefillsFromReapply
{
    /**
     * Attributes copied from the original request into the new form.
     *
     * @return array<int, string>
     */
    abstract protected function reapplyFields(): array;

    public function mount(): void
    {
        parent::mount();

        $sourceId = request()->query('reapply');

        if (! $sourceId) {
            return;
        }

        $resource = static::getResource();
        $source = $resource::getEloquentQuery()->whereKey($sourceId)->first();

        if (! $source || ! $resource::canReapply($source)) {
            return;
        }

        $this->form->fill([
            ...$this->form->getRawState(),
            ...Arr::only($source->attributesToArray(), $this->reapplyFields()),
        ]);
    }
}
