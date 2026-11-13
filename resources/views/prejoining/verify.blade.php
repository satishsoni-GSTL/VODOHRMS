@extends('prejoining.layout')

@section('title', 'Verify your email')
@section('container-class', 'narrow')

@section('content')
    <div class="card stack">
        <div>
            <h1>Welcome, {{ $invite->employee->first_name }}!</h1>
            <p class="muted" style="margin:6px 0 0">
                To open your joining details form, confirm the one-time code we email to
                <strong>{{ $invite->maskedEmail() }}</strong>.
            </p>
        </div>

        @if (session('status'))
            <div class="alert alert-ok">{{ session('status') }}</div>
        @endif

        @error('code')
            <div class="alert alert-error">{{ $message }}</div>
        @enderror

        @if ($codeSent || $errors->has('code'))
            <form method="POST" action="{{ route('prejoining.verify', $token) }}" class="stack">
                @csrf
                <div>
                    <label for="code">6-digit code</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="6" pattern="[0-9]{6}" class="code-input" required autofocus>
                    <p class="muted" style="margin:6px 0 0">The code expires in {{ \App\Services\PrejoiningService::OTP_TTL_MINUTES }} minutes.</p>
                </div>
                <button type="submit" class="btn btn-block">Verify &amp; continue</button>
            </form>

            <form method="POST" action="{{ route('prejoining.send-code', $token) }}" class="row" style="justify-content:center">
                @csrf
                <span class="muted">Didn't get it?</span>
                <button type="submit" class="btn-link">Send a new code</button>
            </form>
        @else
            <form method="POST" action="{{ route('prejoining.send-code', $token) }}">
                @csrf
                <button type="submit" class="btn btn-block">Email me a code</button>
            </form>
        @endif
    </div>
@endsection
