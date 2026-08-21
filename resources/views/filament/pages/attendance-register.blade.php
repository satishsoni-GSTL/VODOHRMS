<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $monthStart = $this->monthStart();
        $dayDates = collect($this->dayNumbers())->mapWithKeys(fn ($day) => [$day => $monthStart->copy()->addDays($day - 1)->toDateString()]);
    @endphp

    <div class="fi-section mb-6 flex flex-wrap items-end gap-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div>
            <label for="register-month" class="mb-2 block text-sm font-medium">Month</label>
            <input
                id="register-month"
                type="month"
                wire:model.live="month"
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            />
        </div>
        <div>
            <label for="register-search" class="mb-2 block text-sm font-medium">Search employee</label>
            <input
                id="register-search"
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Code or name..."
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            />
        </div>

        <div class="flex flex-wrap items-center gap-3 text-xs">
            <span class="text-gray-500 dark:text-gray-400">Each cell: first punch / last punch / work hours. No-punch days show a status instead:</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('L') }}"></span> Leave</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('H') }}"></span> Holiday</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('WO') }}"></span> Weekly Off</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('A') }}"></span> Absent</span>
            <span class="ml-2 text-gray-500 dark:text-gray-400">Hours:</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('P') }}"></span> 8h+ Complete</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('A') }}"></span> Incomplete</span>
        </div>
    </div>

    <div class="fi-section overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full whitespace-nowrap text-left text-xs">
            <thead class="border-b border-gray-200 dark:border-white/10">
                <tr>
                    <th class="sticky left-0 z-10 bg-white p-3 text-sm font-medium dark:bg-gray-900">Code</th>
                    <th class="sticky left-20 z-10 bg-white p-3 text-sm font-medium dark:bg-gray-900">Employee</th>
                    @foreach ($dayDates as $day => $date)
                        <th class="p-2 text-center font-medium">{{ $day }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="sticky left-0 z-10 bg-white p-3 text-sm dark:bg-gray-900">{{ $row['employee']->employee_code }}</td>
                        <td class="sticky left-20 z-10 bg-white p-3 text-sm dark:bg-gray-900">{{ $row['employee']->full_name }}</td>
                        @foreach ($dayDates as $day => $date)
                            @php($cell = $row['days'][$date] ?? ['code' => '', 'label' => '', 'first_in' => null, 'last_out' => null, 'hours' => null])
                            <td class="p-1 text-center align-middle" style="{{ $cell['first_in'] ? '' : $this->cellStyle($cell['code']) }}">
                                @if ($cell['first_in'])
                                    <div class="min-w-[3rem] leading-tight">
                                        <div>{{ \Illuminate\Support\Str::of($cell['first_in'])->limit(5, '') }}</div>
                                        <div>{{ $cell['last_out'] ? \Illuminate\Support\Str::of($cell['last_out'])->limit(5, '') : '—' }}</div>
                                        <div class="mt-0.5 inline-block rounded px-1 font-medium" style="{{ $this->hoursStyle($cell['hours'], $row['min_full_day_hours']) }}">
                                            {{ $cell['hours'] !== null ? number_format($cell['hours'], 2).'h' : '—' }}
                                        </div>
                                    </div>
                                @elseif ($cell['label'] !== '')
                                    <span title="{{ $cell['label'] }}" class="inline-flex min-w-[2.25rem] items-center justify-center rounded px-1.5 py-0.5 font-medium">{{ $cell['code'] }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="100%" class="p-6 text-center text-gray-500">No employees found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rows->links() }}
    </div>
</x-filament-panels::page>
