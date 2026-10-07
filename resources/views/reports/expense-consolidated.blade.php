<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 30px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1f2937; }
        .brand { border-bottom: 3px solid #0c8481; padding-bottom: 8px; margin-bottom: 10px; }
        .brand td { border: none; padding: 0; vertical-align: middle; }
        .brand img { height: 30px; }
        .brand h1 { margin: 0; font-size: 15px; color: #0a6b69; text-align: right; }
        .brand p { margin: 2px 0 0; color: #6b7280; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 4px; border: none; }
        .meta .k { color: #6b7280; width: 90px; }
        .lines th { background: #0c8481; color: #fff; font-weight: bold; padding: 5px 4px; text-align: left; font-size: 8.5px; }
        .lines td { padding: 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .lines tr.excluded td { color: #9ca3af; }
        .num { text-align: right; white-space: nowrap; }
        .att { font-weight: bold; color: #0c8481; }
        .none { color: #d1d5db; }
        .section { margin: 14px 0 5px; font-weight: bold; font-size: 11px; color: #0a6b69; }
        .totals td { padding: 4px; border-bottom: 1px solid #e5e7eb; }
        .totals tr.grand td { font-weight: bold; border-top: 2px solid #0c8481; border-bottom: none; font-size: 10.5px; }
        .badge { padding: 1px 5px; border-radius: 6px; font-size: 8px; background: #eef6f6; color: #0a6b69; }
        .note { margin-top: 12px; color: #6b7280; font-size: 8.5px; }
        .sign td { border: none; padding-top: 40px; width: 33%; text-align: center; color: #6b7280; }
        .sign span { display: block; border-top: 1px solid #9ca3af; margin: 0 20px; padding-top: 3px; }
    </style>
</head>
<body>
    <table class="brand">
        <tr>
            <td>@if ($logo)<img src="{{ $logo }}" alt="GlobalSpace">@endif</td>
            <td>
                <h1>Monthly Expense Statement</h1>
                <p>{{ $monthLabel }} · generated {{ $generatedAt }}</p>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="k">Employee</td><td><strong>{{ $employee->full_name }}</strong> ({{ $employee->employee_code }})</td>
            <td class="k">Company</td><td>{{ $employee->company?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Department</td><td>{{ $employee->department?->name ?? '—' }}</td>
            <td class="k">Designation</td><td>{{ $employee->designation?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Reporting to</td><td>{{ $employee->reportingManager?->full_name ?? '—' }}</td>
            <td class="k">Attachments</td><td>{{ $attachmentCount }} file(s), after this summary</td>
        </tr>
    </table>

    <div class="section">Expense items</div>
    <table class="lines">
        <thead>
            <tr>
                <th>Date</th><th>Head</th><th>Description / Vendor</th><th>Bill no.</th><th>Mode</th>
                <th>Claim</th><th>Status</th><th class="num">Requested</th><th class="num">Approved</th><th>Bill</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $l)
                <tr class="{{ $l['counts'] ? '' : 'excluded' }}">
                    <td>{{ \Carbon\Carbon::parse($l['date'])->format('d M') }}</td>
                    <td>{{ $l['category'] }}</td>
                    <td>{{ $l['description'] }}@if ($l['vendor'])<br><span style="color:#6b7280">{{ $l['vendor'] }}</span>@endif</td>
                    <td>{{ $l['bill_number'] }}</td>
                    <td>{{ $l['payment_mode'] ? ucfirst($l['payment_mode']) : '' }}</td>
                    <td>{{ $l['claim_number'] }}</td>
                    <td><span class="badge">{{ $l['status_label'] }}</span></td>
                    <td class="num">₹{{ number_format($l['requested_amount'], 2) }}</td>
                    <td class="num">{{ $l['approved_amount'] === null ? '—' : '₹'.number_format($l['approved_amount'], 2) }}</td>
                    <td>@if ($l['ref'])<span class="att">{{ $l['ref'] }}</span>@else<span class="none">—</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center;color:#6b7280;padding:14px">No expenses in this month.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (count($byCategory) > 0)
        <div class="section">Summary</div>
        <table class="totals" style="width: 55%">
            @foreach ($byCategory as $name => $amount)
                <tr><td>{{ $name }}</td><td class="num">₹{{ number_format($amount, 2) }}</td></tr>
            @endforeach
            <tr class="grand"><td>Total requested</td><td class="num">₹{{ number_format($totalRequested, 2) }}</td></tr>
            <tr class="grand"><td>Total approved</td><td class="num">₹{{ number_format($totalApproved, 2) }}</td></tr>
        </table>
    @endif

    <p class="note">
        Totals include all claims except rejected and sent-back ones (shown greyed out for reference).
        Bills are attached after this summary in the order A1, A2, … as referenced in the "Bill" column.
    </p>

    <table class="sign">
        <tr>
            <td><span>Employee</span></td>
            <td><span>Reporting manager</span></td>
            <td><span>Finance / HR</span></td>
        </tr>
    </table>
</body>
</html>
