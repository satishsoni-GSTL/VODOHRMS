<x-filament-panels::page>
    <div class="fi-section mb-6 flex flex-wrap items-end gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div>
            <label for="generate-form16-fy" class="mb-2 block text-sm font-medium">Financial Year</label>
            <select
                id="generate-form16-fy"
                wire:model.live="financialYearId"
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
                @foreach ($this->financialYearOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
