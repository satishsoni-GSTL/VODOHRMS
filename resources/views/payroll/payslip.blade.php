<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 40px 48px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        .brand td { vertical-align: middle; padding: 0; }
        .brand img { height: 38px; }
        .brand .company-name { font-size: 13px; color: #4a7fb5; }
        .brand .company-address { font-size: 8px; font-weight: bold; color: #333; text-transform: uppercase; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin: 18px 0 10px; }
        .section { font-size: 13px; font-weight: bold; text-decoration: underline; margin: 14px 0 6px; }
        .details td { padding: 3px 0; width: 50%; }
        .grid th, .grid td { border: 1px solid #000; padding: 5px 6px; }
        .grid th { font-size: 12px; text-align: center; }
        .grid th.amount, .grid td.amount { text-align: right; }
        .grid .total td { font-weight: bold; }
        .words { margin-top: 12px; }
        .footer { margin-top: 24px; text-align: center; font-size: 9px; font-style: italic; }
    </style>
</head>
<body>
    @php
        $earnings = $earningLines->values();
        $deductions = $deductionLines->values();
        $rows = max($earnings->count(), $deductions->count());
        $money = fn ($v) => number_format((float) $v, 2);
    @endphp

    <table class="brand">
        <tr>
            @if ($logo)
                <td style="width: 45%;"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <div class="company-name">{{ $company->name ?? 'VODOHRMS' }}</div>
                @if ($company?->address)
                    <div class="company-address">{{ $company->address }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="title">SALARY SLIP – {{ strtoupper($monthLabel) }}</div>

    <div class="section">EMPLOYEE DETAILS</div>
    <table class="details">
        <tr>
            <td><strong>Employee Code:</strong> {{ $employee->employee_code }}</td>
            <td><strong>Department:</strong> {{ $employee->department?->name }}</td>
        </tr>
        <tr>
            <td><strong>Employee Name:</strong> {{ $employee->full_name }}</td>
            <td><strong>Designation:</strong> {{ $employee->designation?->name }}</td>
        </tr>
        <tr>
            <td><strong>Pay Period:</strong> {{ $payPeriod }}</td>
            <td><strong>Annual CTC:</strong> {{ $annualCtc !== null ? '₹'.$money($annualCtc) : '—' }}</td>
        </tr>
        <tr>
            <td><strong>Paid Days:</strong> {{ rtrim(rtrim(number_format((float) $runEmployee->paid_days, 2), '0'), '.') }}</td>
            <td><strong>LOP Days:</strong> {{ rtrim(rtrim(number_format((float) $runEmployee->lop_days, 2), '0'), '.') }}</td>
        </tr>
    </table>

    <table style="margin-top: 14px;">
        <tr>
            <td class="section" style="width: 51%; margin: 0;">EARNINGS</td>
            <td class="section" style="margin: 0;">DEDUCTIONS</td>
        </tr>
    </table>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 32%;">Particulars</th>
                <th class="amount" style="width: 19%;">Amount (₹)</th>
                <th style="width: 29%;">Particulars</th>
                <th class="amount" style="width: 20%;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $rows; $i++)
                <tr>
                    <td>{{ $earnings[$i]->label ?? '' }}</td>
                    <td class="amount">{{ isset($earnings[$i]) ? $money($earnings[$i]->amount) : '' }}</td>
                    <td>{{ $deductions[$i]->label ?? '' }}</td>
                    <td class="amount">{{ isset($deductions[$i]) ? $money($deductions[$i]->amount) : '' }}</td>
                </tr>
            @endfor
            <tr class="total">
                <td>GROSS SALARY</td>
                <td class="amount">{{ $money($runEmployee->gross_earnings) }}</td>
                <td>TOTAL DEDUCTIONS</td>
                <td class="amount">{{ $money($runEmployee->total_deductions) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section">NET SALARY</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 51%;">Particulars</th>
                <th class="amount">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="total">
                <td>Net In-Hand Salary</td>
                <td class="amount">{{ $money($runEmployee->net_pay) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="words"><strong>Net Salary (In Words):</strong> {{ $netPayInWords }}</div>

    <div class="footer">(This is a computer-generated salary slip and does not require a physical signature.)</div>
</body>
</html>
