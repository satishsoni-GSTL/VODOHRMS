<x-filament-panels::page>
    <div class="fi-section flex flex-wrap items-end gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div>
            <label for="my-leave-year" class="mb-2 block text-sm font-medium">Year</label>
            <select
                id="my-leave-year"
                wire:model.live="year"
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
                @foreach ($this->yearOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Leave Balances</h3>
        {{ $this->table }}
    </div>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-6 py-3 text-sm font-semibold text-gray-950 dark:border-white/10 dark:text-white">
            My Leave Applications ({{ $year }})
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                    <th class="px-6 py-2 font-medium">Type</th>
                    <th class="px-6 py-2 font-medium">From</th>
                    <th class="px-6 py-2 font-medium">To</th>
                    <th class="px-6 py-2 font-medium text-right">Days</th>
                    <th class="px-6 py-2 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($this->myApplications() as $app)
                    <tr>
                        <td class="px-6 py-3 text-gray-950 dark:text-white">{{ $app->leaveType?->name }}</td>
                        <td class="px-6 py-3">{{ $app->from_date->format('d M Y') }}</td>
                        <td class="px-6 py-3">{{ $app->to_date->format('d M Y') }}</td>
                        <td class="px-6 py-3 text-right tabular-nums">{{ $app->days }}</td>
                        <td class="px-6 py-3">
                            <x-filament::badge :color="$this->statusColor($app->status)">
                                {{ ucfirst(str_replace('_', ' ', $app->status)) }}
                            </x-filament::badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-gray-500 dark:text-gray-400">No leave applications this year.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
