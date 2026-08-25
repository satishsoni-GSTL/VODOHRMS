<x-filament-panels::page>
    @php
        $days = $this->rows();
        $totals = $this->totals();
    @endphp

    <div class="fi-section mb-6 flex flex-wrap items-end gap-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div>
            <label for="my-attendance-month" class="mb-2 block text-sm font-medium">Month</label>
            <input
                id="my-attendance-month"
                type="month"
                wire:model.live="month"
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

    <div class="fi-section mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-9">
        @foreach ([
            ['label' => 'Present', 'value' => $totals['P'], 'code' => 'P'],
            ['label' => 'Half Day', 'value' => $totals['HD'], 'code' => 'HD'],
            ['label' => 'WFH', 'value' => $totals['WFH'], 'code' => 'WFH'],
            ['label' => 'On Duty', 'value' => $totals['OD'], 'code' => 'OD'],
            ['label' => 'Leave', 'value' => $totals['L'], 'code' => 'L'],
            ['label' => 'Holiday', 'value' => $totals['H'], 'code' => 'H'],
            ['label' => 'Weekly Off', 'value' => $totals['WO'], 'code' => 'WO'],
            ['label' => 'Absent', 'value' => $totals['A'], 'code' => 'A'],
        ] as $stat)
            <div class="rounded-xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-2xl font-semibold" style="{{ $this->cellStyle($stat['code']) }}border-radius:0.5rem;">{{ $stat['value'] }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
            </div>
        @endforeach
        <div class="rounded-xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-2xl font-semibold">{{ $totals['hours'] }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total Hours (avg {{ $totals['avg_hours'] }}/day)</div>
        </div>
    </div>

    <div class="fi-section overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 dark:border-white/10">
                <tr>
                    <th class="p-3 font-medium">Date</th>
                    <th class="p-3 font-medium">Day</th>
                    <th class="p-3 font-medium">Status</th>
                    <th class="p-3 font-medium">First In</th>
                    <th class="p-3 font-medium">Last Out</th>
                    <th class="p-3 font-medium">Hours</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($days as $date => $cell)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="p-3">{{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</td>
                        <td class="p-3">{{ \Illuminate\Support\Carbon::parse($date)->format('l') }}</td>
                        <td class="p-3">
                            @if ($cell['code'] !== '')
                                <span
                                    style="{{ $this->cellStyle($cell['code']) }}"
                                    class="inline-flex items-center justify-center rounded px-2 py-0.5 text-xs font-medium"
                                >{{ $cell['code'] }}</span>
                            @else
                                <span class="text-gray-400">&mdash;</span>
                            @endif
                        </td>
                        <td class="p-3">{{ $cell['first_in'] ?? '—' }}</td>
                        <td class="p-3">{{ $cell['last_out'] ?? '—' }}</td>
                        <td class="p-3">{{ $cell['hours'] !== null ? number_format($cell['hours'], 2) : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-gray-500">No employee record linked to your account.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
