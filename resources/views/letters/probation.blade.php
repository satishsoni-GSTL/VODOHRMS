@php
    $confirmed = $review->recommendation === \App\Models\ProbationReview::RECOMMEND_CONFIRM;
    $name = trim(preg_replace('/\s+/', ' ', $employee->full_name));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; line-height: 1.6; margin: 10px 20px; }
        .header { text-align: center; border-bottom: 2px solid #1f2937; padding-bottom: 8px; margin-bottom: 24px; }
        .header h1 { font-size: 17px; margin: 0; }
        .header p { margin: 2px 0; color: #6b7280; font-size: 11px; }
        .meta td { padding: 1px 0; vertical-align: top; }
        .subject { font-weight: bold; text-decoration: underline; margin: 22px 0 14px; }
        .sign { margin-top: 48px; }
        .footer { margin-top: 40px; font-size: 9px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logo)
            <img src="{{ $logo }}" alt="" style="height: 40px; margin-bottom: 6px;">
        @endif
        <h1>{{ $company->name ?? config('app.name') }}</h1>
        @if (! empty($company?->address))
            <p>{{ is_array($company->address) ? implode(', ', array_filter($company->address)) : $company->address }}</p>
        @endif
    </div>

    <table class="meta">
        <tr><td><strong>Date:</strong> {{ $issuedOn }}</td></tr>
        <tr><td><strong>Ref:</strong> PR/{{ $employee->employee_code }}/{{ $review->id }}</td></tr>
    </table>

    <p style="margin-top: 18px;">
        <strong>{{ $name }}</strong><br>
        Employee Code: {{ $employee->employee_code }}<br>
        @if ($employee->designation) {{ $employee->designation->name }}<br> @endif
        @if ($employee->department) {{ $employee->department->name }} @endif
    </p>

    <p class="subject">Subject: {{ $confirmed ? 'Confirmation of Employment' : 'Extension of Probation Period' }}</p>

    <p>Dear {{ $employee->first_name }},</p>

    @if ($confirmed)
        <p>
            With reference to your appointment dated {{ $employee->date_of_joining?->format('d M Y') }}, we are pleased to
            inform you that, on successful completion of your probation period, your services are confirmed with effect
            from <strong>{{ $review->effective_date->format('d M Y') }}</strong>.
        </p>
        <p>
            All other terms and conditions of your appointment remain unchanged. We appreciate your contribution and look
            forward to your continued association with the organisation.
        </p>
    @else
        <p>
            With reference to your appointment dated {{ $employee->date_of_joining?->format('d M Y') }}, your probation
            period was due to end on {{ $review->probation_end_date->format('d M Y') }}. Following a review of your
            performance, your probation period has been extended by <strong>{{ $review->extension_days }} days</strong>,
            up to <strong>{{ $review->effective_date->format('d M Y') }}</strong>.
        </p>
        @if ($review->improvement_areas)
            <p>During this period, you are expected to focus on the following:</p>
            <p style="margin-left: 16px;">{!! nl2br(e($review->improvement_areas)) !!}</p>
        @endif
        <p>
            Your performance will be reviewed again at the end of the extended period. All other terms and conditions of
            your appointment remain unchanged.
        </p>
    @endif

    <p>We wish you all the best.</p>

    <div class="sign">
        For <strong>{{ $company->name ?? config('app.name') }}</strong><br><br><br>
        Authorised Signatory<br>
        Human Resources
    </div>

    <div class="footer">This is a system-generated letter.</div>
</body>
</html>
