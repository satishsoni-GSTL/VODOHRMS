<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 12px; }
        .header h1 { font-size: 16px; margin: 0; }
        .header h2 { font-size: 13px; margin: 2px 0; }
        .header p { margin: 2px 0; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        td, th { padding: 4px 6px; border: 1px solid #d1d5db; text-align: left; }
        .meta-table td { border: none; padding: 2px 6px; }
        .section-title { font-weight: bold; margin-top: 12px; margin-bottom: 4px; font-size: 12px; background: #f3f4f6; padding: 4px 6px; }
        .totals td { font-weight: bold; }
        .amount { text-align: right; }
        .net-tax { margin-top: 12px; padding: 8px; background: #f3f4f6; font-weight: bold; }
        .disclaimer { margin-top: 16px; padding: 8px; border: 1px solid #d1d5db; font-size: 9px; color: #4b5563; }
        .disclaimer strong { color: #1f2937; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company->name ?? 'VODOHRMS' }}</h1>
        <p>{{ $company->address ?? '' }}</p>
        <p>PAN: {{ $company->pan ?? '—' }} &nbsp;|&nbsp; TAN: {{ $company->tan ?? '—' }}</p>
        <h2>Form 16 — Certificate of Tax Deducted at Source (Part B)</h2>
        <p>Financial Year {{ $financialYear->name }} &nbsp;|&nbsp; Assessment Year {{ $financialYear->assessment_year }}</p>
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>Employee Name:</strong> {{ $employee->full_name }}</td>
            <td><strong>Employee Code:</strong> {{ $employee->employee_code }}</td>
        </tr>
        <tr>
            <td><strong>Designation:</strong> {{ $employee->designation?->name }}</td>
            <td><strong>PAN:</strong> {{ $pan ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Tax Regime Followed:</strong> {{ ucfirst($regime) }} Regime</td>
            <td><strong>Period:</strong> {{ \Carbon\Carbon::parse($financialYear->start_date)->format('d-M-Y') }} to {{ \Carbon\Carbon::parse($financialYear->end_date)->format('d-M-Y') }}</td>
        </tr>
    </table>

    <div class="section-title">Part B — Details of Salary Paid and Tax Computed</div>
    <table>
        <tbody>
            <tr><td>Gross Salary Paid / Payable for the Year</td><td class="amount">{{ number_format($projection->projected_annual_income, 2) }}</td></tr>
            <tr><td>Less: Standard Deduction</td><td class="amount">{{ number_format($standardDeduction, 2) }}</td></tr>
            @if ($regime === 'old' && $sections->isNotEmpty())
                @foreach ($sections as $section)
                    <tr><td>Less: {{ $section['name'] }} ({{ $section['code'] }})</td><td class="amount">{{ number_format($section['amount'], 2) }}</td></tr>
                @endforeach
            @endif
            <tr class="totals"><td>Total Exemptions / Deductions</td><td class="amount">{{ number_format($projection->total_exemptions, 2) }}</td></tr>
            <tr class="totals"><td>Taxable Income</td><td class="amount">{{ number_format($projection->taxable_income, 2) }}</td></tr>
            <tr><td>Tax on Total Income</td><td class="amount">{{ number_format($projection->tax_before_rebate, 2) }}</td></tr>
            <tr><td>Less: Rebate under Section 87A</td><td class="amount">{{ number_format($projection->rebate, 2) }}</td></tr>
            <tr><td>Add: Surcharge</td><td class="amount">{{ number_format($projection->surcharge, 2) }}</td></tr>
            <tr><td>Add: Health &amp; Education Cess</td><td class="amount">{{ number_format($projection->cess, 2) }}</td></tr>
            <tr class="totals"><td>Total Tax Payable</td><td class="amount">{{ number_format($projection->final_tax, 2) }}</td></tr>
            <tr><td>Total Tax Deducted at Source (till date)</td><td class="amount">{{ number_format($projection->tds_deducted_till_date, 2) }}</td></tr>
            <tr class="totals"><td>Balance Tax Payable / (Refundable)</td><td class="amount">{{ number_format($projection->remaining_tax, 2) }}</td></tr>
        </tbody>
    </table>

    <div class="net-tax">
        Total Tax Deducted for the Year: ₹{{ number_format($projection->tds_deducted_till_date, 2) }} ({{ $totalTaxInWords }})
    </div>

    <div class="section-title">Part A (Summary) — Quarter-wise Details of Tax Deducted and Deposited</div>
    <table>
        <thead>
            <tr><th>Quarter</th><th>Months</th><th class="amount">TDS Deducted (₹)</th></tr>
        </thead>
        <tbody>
            @foreach ($quarters as $quarter)
                <tr>
                    <td>{{ $quarter['label'] }}</td>
                    <td>{{ implode(', ', array_map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M-Y'), $quarter['months'])) }}</td>
                    <td class="amount">{{ number_format($quarter['amount'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="2">Total</td>
                <td class="amount">{{ number_format(collect($quarters)->sum('amount'), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="disclaimer">
        <strong>Note:</strong> This certificate is generated from the employer's payroll and income-tax records as Part B
        (salary &amp; tax computation) together with a quarter-wise TDS summary in the format of Part A. It is
        <strong>not</strong> the signed Part A certificate issued via the Income Tax Department's TRACES portal — that
        requires the employer's quarterly Form 24Q TDS returns to have been filed and is downloadable only from TRACES.
        This document should be countersigned by the employer / authorised signatory and read together with the
        TRACES-issued Part A to constitute a complete, legally valid Form 16.
    </div>

    <table style="margin-top: 16px; border: none;">
        <tr style="border: none;">
            <td style="border: none;">Generated on: {{ $generatedOn->format('d-M-Y H:i') }}</td>
            <td style="border: none; text-align: right;">Authorised Signatory: ____________________</td>
        </tr>
    </table>
</body>
</html>
