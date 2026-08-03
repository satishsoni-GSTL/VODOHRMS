<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $monthStart = $this->monthStart();
        $dayDates = collect($this->dayNumbers())->mapWithKeys(fn ($day) => [$day => $monthStart->copy()->addDays($day - 1)->toDateString()]);
    @endphp

    <div class="fi-section mb-6 flex flex-wrap items-end gap-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div>
            <label for="attendance-month" class="mb-2 block text-sm font-medium">Month</label>
            <input
                id="attendance-month"
                type="month"
                wire:model.live="month"
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            />
        </div>
        <div>
            <label for="attendance-search" class="mb-2 block text-sm font-medium">Search employee</label>
            <input
                id="attendance-search"
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Code or name..."
                class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
            />
        </div>

        <div class="flex flex-wrap items-center gap-3 text-xs">
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('P') }}"></span> Present</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('HD') }}"></span> Half Day</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('MP') }}"></span> Missing Punch</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('WFH') }}"></span> WFH / On Duty</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('L') }}"></span> Leave</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('H') }}"></span> Holiday</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('WO') }}"></span> Weekly Off</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="{{ $this->cellStyle('A') }}"></span> Absent</span>
        </div>
    </div>

    <div class="fi-section overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full whitespace-nowrap text-left text-sm">
            <thead class="border-b border-gray-200 dark:border-white/10">
                <tr>
                    <th class="sticky left-0 z-10 bg-white p-3 font-medium dark:bg-gray-900">Code</th>
                    <th class="sticky left-20 z-10 bg-white p-3 font-medium dark:bg-gray-900">Employee</th>
                    @foreach ($dayDates as $day => $date)
                        <th class="p-2 text-center font-medium">{{ $day }}</th>
                    @endforeach
                    <th class="p-2 text-center font-medium">P</th>
                    <th class="p-2 text-center font-medium">HD</th>
                    <th class="p-2 text-center font-medium">L</th>
                    <th class="p-2 text-center font-medium">H</th>
                    <th class="p-2 text-center font-medium">WO</th>
                    <th class="p-2 text-center font-medium">A</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="sticky left-0 z-10 bg-white p-3 dark:bg-gray-900">{{ $row['employee']->employee_code }}</td>
                        <td class="sticky left-20 z-10 bg-white p-3 dark:bg-gray-900">{{ $row['employee']->full_name }}</td>
                        @foreach ($dayDates as $day => $date)
                            @php($cell = $row['days'][$date] ?? ['code' => '', 'label' => ''])
                            <td class="p-1 text-center">
                                @if ($cell['label'] !== '')
                                    <span
                                        title="{{ $cell['label'] }}"
                                        style="{{ $this->cellStyle($cell['code']) }}"
                                        class="inline-flex min-w-[2.25rem] items-center justify-center rounded px-1.5 py-0.5 text-xs font-medium"
                                    >{{ $cell['code'] }}</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="p-2 text-center">{{ $row['totals']['P'] }}</td>
                        <td class="p-2 text-center">{{ $row['totals']['HD'] }}</td>
                        <td class="p-2 text-center">{{ $row['totals']['L'] }}</td>
                        <td class="p-2 text-center">{{ $row['totals']['H'] }}</td>
                        <td class="p-2 text-center">{{ $row['totals']['WO'] }}</td>
                        <td class="p-2 text-center">{{ $row['totals']['A'] }}</td>
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
