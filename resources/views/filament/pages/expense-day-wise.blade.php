@php
    use App\Models\ExpenseClaim;

    $requestedTotal = $lines->sum('requested_amount');
    $approvedTotal = $lines->sum(fn ($line) => $line['approved_amount'] ?? 0);
@endphp

<div class="space-y-4">
    <div class="flex justify-end">
        <a
            href="{{ $downloadUrl }}"
            target="_blank"
            class="fi-btn inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500"
        >
            Export to Excel
        </a>
    </div>

    @if ($lines->isEmpty())
        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No expense lines for this employee in the selected month.</p>
    @else
        <div class="overflow-x-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr class="text-left">
                        <th class="whitespace-nowrap px-3 py-2 font-medium">Date</th>
                        <th class="whitespace-nowrap px-3 py-2 font-medium">Head</th>
                        <th class="px-3 py-2 font-medium">Description</th>
                        <th class="whitespace-nowrap px-3 py-2 font-medium">Vendor</th>
                        <th class="whitespace-nowrap px-3 py-2 font-medium">Bill No.</th>
                        <th class="whitespace-nowrap px-3 py-2 font-medium">Mode</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-medium">Requested</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-medium">Approved</th>
                        <th class="whitespace-nowrap px-3 py-2 font-medium">Claim</th>
                        <th class="whitespace-nowrap px-3 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($lines as $line)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ \Illuminate\Support\Carbon::parse($line['date'])->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $line['category'] }}</td>
                            <td class="px-3 py-2">{{ $line['description'] ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $line['vendor'] ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $line['bill_number'] ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $line['payment_mode'] ? ucfirst($line['payment_mode']) : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">₹{{ number_format($line['requested_amount'], 2) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $line['approved_amount'] === null ? '—' : '₹' . number_format($line['approved_amount'], 2) }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $line['claim_number'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ ExpenseClaim::STATUSES[$line['status']] ?? $line['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-semibold dark:bg-gray-800">
                    <tr>
                        <td class="px-3 py-2" colspan="6">Total</td>
                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">₹{{ number_format($requestedTotal, 2) }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">₹{{ number_format($approvedTotal, 2) }}</td>
                        <td class="px-3 py-2" colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
