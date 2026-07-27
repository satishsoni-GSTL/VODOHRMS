<x-filament-panels::page>
    <div class="fi-section mb-6 flex flex-wrap items-end gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div>
            <label for="my-payroll-fy" class="mb-2 block text-sm font-medium">Financial Year</label>
            <select
                id="my-payroll-fy"
                wire:model.live="financialYear"
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
                @foreach ($this->financialYearOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <a
            href="{{ route('my-payroll.download', ['financial_year' => $financialYear]) }}"
            target="_blank"
            class="fi-btn inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
        >
            Export My Payroll Sheet
        </a>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
