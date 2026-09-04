@php($s = $this->summary())
<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Payroll Run</p>
                <p class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $this->payrollRun->payroll_month }} — {{ $this->payrollRun->company?->name }}
                </p>
            </div>
            <div class="flex gap-6 text-sm">
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Deductions</p>
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $s['total'] }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Active</p>
                    <p class="font-semibold text-gray-950 dark:text-white">₹{{ number_format($s['active_amount'], 2) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Waived</p>
                    <p class="font-semibold text-warning-600 dark:text-warning-400">
                        {{ $s['waived'] }} · ₹{{ number_format($s['waived_amount'], 2) }}
                    </p>
                </div>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Granting an exception removes that deduction from <strong>this run only</strong>. The payroll input,
            salary-structure line or statutory rule is unchanged and applies again next month. Recalculate the run
            after changing exceptions.
        </p>
    </div>

    @forelse ($this->rowsByEmployee() as $employee => $rows)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-6 py-3 font-medium text-gray-950 dark:border-white/10 dark:text-white">
                {{ $employee }}
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-2 font-medium">Deduction</th>
                        <th class="px-6 py-2 font-medium">Source</th>
                        <th class="px-6 py-2 font-medium text-right">Monthly Amount</th>
                        <th class="px-6 py-2 font-medium">Status</th>
                        <th class="px-6 py-2 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($rows as $row)
                        <tr @class(['bg-warning-50/50 dark:bg-warning-500/5' => $row['exception']])>
                            <td class="px-6 py-3 text-gray-950 dark:text-white">{{ $row['label'] }}</td>
                            <td class="px-6 py-3">
                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs dark:bg-white/10">
                                    {{ \App\Models\PayrollRunDeductionException::SOURCES[$row['source_type']] ?? $row['source_type'] }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right tabular-nums text-gray-950 dark:text-white">
                                ₹{{ number_format($row['amount'], 2) }}
                            </td>
                            <td class="px-6 py-3">
                                @if ($row['exception'])
                                    <span class="font-medium text-warning-600 dark:text-warning-400">Waived</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['exception']->reason }}
                                        @if ($row['exception']->waivedBy) · {{ $row['exception']->waivedBy->name }} @endif
                                    </span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Active</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                @if ($row['exception'])
                                    {{ ($this->restoreAction)(['id' => $row['exception']->id]) }}
                                @else
                                    {{ ($this->waiveAction)(['key' => $row['key']]) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="fi-section rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
            No other deductions found for this run.
        </div>
    @endforelse

    <x-filament-actions::modals />
</x-filament-panels::page>
