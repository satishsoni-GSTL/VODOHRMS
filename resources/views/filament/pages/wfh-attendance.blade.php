<x-filament-panels::page>
    @php
        $today = $this->todayAttendance();
        $isWfhToday = $today?->status === \App\Models\Attendance::STATUS_WFH;
    @endphp

    <div class="fi-section mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold">Today — {{ now()->format('d M Y, l') }}</h3>

        @if (! $isWfhToday)
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                You do not have an approved Work From Home request for today. Submit one under
                <span class="font-medium">Attendance &rarr; Work From Home</span> and have your manager approve it before punching in.
            </p>
        @else
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div class="text-sm">
                    <span class="text-gray-500 dark:text-gray-400">In Time:</span>
                    <span class="font-medium">{{ $today->first_in ?? '—' }}</span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Out Time:</span>
                    <span class="font-medium">{{ $today->display_last_out ?? '—' }}</span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Hours So Far:</span>
                    <span class="font-medium">{{ $today->effective_hours ?? '—' }}</span>
                </div>

                <div class="ml-auto flex gap-2">
                    <button
                        type="button"
                        wire:click="checkIn"
                        class="fi-btn inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        Check In
                    </button>
                    <button
                        type="button"
                        wire:click="checkOut"
                        class="fi-btn inline-flex items-center justify-center gap-1.5 rounded-lg bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-600"
                    >
                        Check Out
                    </button>
                </div>
            </div>
        @endif
    </div>

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="mb-4 text-base font-semibold">This Month's Work From Home Attendance</h3>

        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">In Time</th>
                        <th class="px-3 py-2">Out Time</th>
                        <th class="px-3 py-2">Hours</th>
                        <th class="px-3 py-2">Late Mark</th>
                        <th class="px-3 py-2">Work Hours Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->monthHistory() as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                            <td class="px-3 py-2">{{ $row['first_in'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $row['last_out'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $row['effective_hours'] ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @if ($row['late_mark'])
                                    <span class="fi-badge inline-flex items-center rounded-md bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                                        Late by {{ $row['late_minutes'] }} min
                                    </span>
                                @else
                                    <span class="fi-badge inline-flex items-center rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                        On Time
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if ($row['completed'])
                                    <span class="fi-badge inline-flex items-center rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                        Completed
                                    </span>
                                @else
                                    <span class="fi-badge inline-flex items-center rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                        Incomplete
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No Work From Home attendance recorded this month.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
