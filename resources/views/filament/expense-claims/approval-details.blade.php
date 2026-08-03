<div class="mb-4 space-y-3">
    <div class="grid grid-cols-2 gap-2 text-sm">
        <div><span class="text-gray-500">Employee:</span> {{ $claim->employee?->full_name }}</div>
        <div><span class="text-gray-500">Claim #:</span> {{ $claim->claim_number }}</div>
        <div><span class="text-gray-500">Total Requested:</span> ₹{{ number_format($claim->total_requested_amount, 2) }}</div>
        <div><span class="text-gray-500">Date:</span> {{ optional($claim->claim_date)->format('d M Y') }}</div>
    </div>

    <div class="overflow-x-auto rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                <tr>
                    <th class="p-2 font-medium">Category</th>
                    <th class="p-2 font-medium">Date</th>
                    <th class="p-2 font-medium">Amount</th>
                    <th class="p-2 font-medium">Vendor</th>
                    <th class="p-2 font-medium">Receipt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($claim->lines as $line)
                    <tr class="border-b border-gray-100 last:border-b-0 dark:border-white/5">
                        <td class="p-2">{{ $line->category?->name }}</td>
                        <td class="p-2">{{ optional($line->expense_date)->format('d M Y') }}</td>
                        <td class="p-2">₹{{ number_format($line->requested_amount, 2) }}</td>
                        <td class="p-2">{{ $line->vendor }}</td>
                        <td class="p-2">
                            @if ($line->receipt_path)
                                <a href="{{ route('expense-receipts.download', $line) }}" target="_blank" class="font-medium text-primary-600 hover:underline">View</a>
                            @else
                                <span class="text-gray-400">None</span>
                            @endif
                        </td>
                    </tr>
                    @if ($line->description)
                        <tr class="border-b border-gray-100 last:border-b-0 dark:border-white/5">
                            <td colspan="5" class="p-2 pt-0 text-xs text-gray-500">{{ $line->description }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
