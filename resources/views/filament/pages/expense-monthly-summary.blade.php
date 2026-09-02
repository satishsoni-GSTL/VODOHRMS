<x-filament-panels::page>
    @php($summary = $this->summary())

    <div class="fi-section mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <label for="expense-summary-month" class="mb-2 block text-sm font-medium">Month</label>
        <input
            id="expense-summary-month"
            type="month"
            wire:model.live="month"
            class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
        />
    </div>

    @if (! empty($summary['rows']))
        <div class="fi-section mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="mb-3 text-sm font-semibold text-gray-500 dark:text-gray-400">Totals for {{ $month }} (claimed amounts)</h3>
            <div class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($summary['categories'] as $categoryId => $categoryName)
                    <div class="flex items-baseline justify-between border-b border-gray-100 py-1 dark:border-gray-800">
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $categoryName }}</span>
                        <span class="text-sm font-medium tabular-nums">₹{{ number_format($summary['totals'][$categoryId] ?? 0, 2) }}</span>
                    </div>
                @endforeach
                <div class="flex items-baseline justify-between border-b-2 border-primary-500 py-1">
                    <span class="text-sm font-semibold">Grand Total</span>
                    <span class="text-sm font-bold tabular-nums">₹{{ number_format($summary['grand_total'], 2) }}</span>
                </div>
            </div>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
