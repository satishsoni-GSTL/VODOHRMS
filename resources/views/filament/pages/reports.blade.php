<x-filament-panels::page>
    <div class="fi-section mb-6 flex flex-wrap gap-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div>
            <label for="report-month" class="mb-2 block text-sm font-medium">Month (applies to month-scoped reports)</label>
            <input
                id="report-month"
                type="month"
                wire:model.live="month"
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            />
        </div>
        <div>
            <label for="report-fy" class="mb-2 block text-sm font-medium">Financial Year (applies to FY-scoped reports)</label>
            <select
                id="report-fy"
                wire:model.live="financialYear"
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
                @foreach ($this->financialYearOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->availableReports() as $report)
            <div class="fi-section flex flex-col justify-between rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div>
                    <h3 class="text-base font-semibold">{{ $report['label'] }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $report['description'] }}</p>
                </div>
                <a
                    href="{{ route('reports.download', ($report['needsFinancialYear'] ?? false) ? ['type' => $report['type'], 'financial_year' => $financialYear] : ['type' => $report['type'], 'month' => $month]) }}"
                    target="_blank"
                    class="fi-btn mt-4 inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                >
                    Download
                </a>
            </div>
        @empty
            <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                No reports are available for your role.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
