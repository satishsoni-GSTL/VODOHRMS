<x-filament-panels::page>
    <div class="fi-section flex flex-wrap items-end gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div>
            <label for="approval-type" class="mb-2 block text-sm font-medium">Request type</label>
            <select
                id="approval-type"
                wire:model.live="typeFilter"
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
                <option value="">All types</option>
                @foreach ($this->typeOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $this->actionableInstances()->count() }} request(s) awaiting your action
        </p>
    </div>

    @forelse ($this->groupedByEmployee() as $employee => $instances)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-3 dark:border-white/10">
                <div class="font-medium text-gray-950 dark:text-white">
                    {{ $employee }}
                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ $instances->count() }} pending</span>
                </div>
                @if ($instances->count() > 1)
                    {{ ($this->approveAllAction)(['ids' => $instances->pluck('id')->all()]) }}
                @endif
            </div>

            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($instances as $instance)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                {{ $instance->workflowDefinition?->name }}
                            </p>
                            <p class="text-sm text-gray-950 dark:text-white">{{ $this->summarize($instance) }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Submitted {{ $instance->created_at?->diffForHumans() }} · Level {{ $instance->current_level }}
                                @if (filled($instance->requestable->reason ?? null))
                                    · {{ \Illuminate\Support\Str::limit($instance->requestable->reason, 60) }}
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            {{ ($this->approveAction)(['id' => $instance->id]) }}
                            {{ ($this->rejectAction)(['id' => $instance->id]) }}
                            {{ ($this->sendBackAction)(['id' => $instance->id]) }}
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="fi-section rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
            Nothing awaiting your approval right now.
        </div>
    @endforelse

    <x-filament-actions::modals />
</x-filament-panels::page>
