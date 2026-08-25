<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Team Attendance Today</x-slot>
        <x-slot name="headerEnd">
            <a href="{{ $this->registerUrl() }}" class="fi-link text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                View full register &rarr;
            </a>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 dark:border-white/10">
                    <tr>
                        <th class="p-2 font-medium">Employee</th>
                        <th class="p-2 font-medium">Status</th>
                        <th class="p-2 font-medium">First In</th>
                        <th class="p-2 font-medium">Last Out</th>
                        <th class="p-2 font-medium">Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows() as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="p-2">
                                <div class="font-medium">{{ $row['employee']->full_name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['employee']->employee_code }}</div>
                            </td>
                            <td class="p-2">
                                @if ($row['cell']['code'] !== '')
                                    <span
                                        style="{{ $this->cellStyle($row['cell']['code']) }}"
                                        class="inline-flex items-center justify-center rounded px-2 py-0.5 text-xs font-medium"
                                    >{{ $row['cell']['code'] }}</span>
                                @else
                                    <span class="text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="p-2">{{ $row['cell']['first_in'] ?? '—' }}</td>
                            <td class="p-2">{{ $row['cell']['last_out'] ?? '—' }}</td>
                            <td class="p-2">{{ $row['cell']['hours'] !== null ? number_format($row['cell']['hours'], 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-gray-500">No direct reports.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
