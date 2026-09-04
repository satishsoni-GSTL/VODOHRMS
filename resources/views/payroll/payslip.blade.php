<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { font-size: 16px; margin: 0; }
        .header p { margin: 2px 0; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        td, th { padding: 4px 6px; border: 1px solid #d1d5db; text-align: left; }
        .meta-table td { border: none; padding: 2px 6px; }
        .totals td { font-weight: bold; }
        .section-title { font-weight: bold; margin-top: 12px; margin-bottom: 4px; }
        .net-pay { margin-top: 16px; padding: 8px; background: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company->name ?? 'VODOHRMS' }}</h1>
        <p>Payslip for {{ $monthLabel }}</p>
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>Employee Name:</strong> {{ $employee->full_name }}</td>
            <td><strong>Employee Code:</strong> {{ $employee->employee_code }}</td>
        </tr>
        <tr>
            <td><strong>Department:</strong> {{ $employee->department?->name }}</td>
            <td><strong>Designation:</strong> {{ $employee->designation?->name }}</td>
        </tr>
        <tr>
            <td><strong>PAN:</strong> {{ $pan }}</td>
            <td><strong>UAN:</strong> {{ $uan }}</td>
        </tr>
        <tr>
            <td><strong>Bank Account:</strong> {{ $maskedAccount }}</td>
            <td><strong>Paid Days / LOP:</strong> {{ $runEmployee->paid_days }} /
                {{ $runEmployee->lop_days }}{{ $runEmployee->lop_amount > 0 ? ' (−₹'.number_format($runEmployee->lop_amount, 2).')' : '' }}
            </td>
        </tr>
    </table>

    <div class="section-title">Earnings</div>
    <table>
        <thead><tr><th>Component</th><th>Amount</th></tr></thead>
        <tbody>
            @foreach ($earningLines as $line)
                <tr><td>{{ $line->label }}</td><td>{{ number_format($line->amount, 2) }}</td></tr>
            @endforeach
            <tr class="totals"><td>Gross Earnings</td><td>{{ number_format($runEmployee->gross_earnings, 2) }}</td></tr>
        </tbody>
    </table>

    <div class="section-title">Deductions</div>
    <table>
        <thead><tr><th>Component</th><th>Amount</th></tr></thead>
        <tbody>
            @foreach ($deductionLines as $line)
                <tr><td>{{ $line->label }}</td><td>{{ number_format($line->amount, 2) }}</td></tr>
            @endforeach
            <tr class="totals"><td>Total Deductions</td><td>{{ number_format($runEmployee->total_deductions, 2) }}</td></tr>
        </tbody>
    </table>

    <div class="net-pay">
        Net Salary: ₹{{ number_format($runEmployee->net_pay, 2) }} ({{ $netPayInWords }})
    </div>

    <table style="margin-top: 12px;">
        <tr>
            <td><strong>YTD Earnings:</strong> {{ number_format($ytdEarnings, 2) }}</td>
            <td><strong>YTD TDS:</strong> {{ number_format($ytdTds, 2) }}</td>
        </tr>
    </table>
</body>
</html>
