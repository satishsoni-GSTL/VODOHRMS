@extends('fileshare.layout')

@section('title', 'Verify your email')
@section('container-class', 'narrow')

@section('content')
    <div class="card stack">
        <div>
            <h1>Verify it's you</h1>
            <p class="muted" style="margin:6px 0 0">
                <strong>{{ $file->owner->name }}</strong> shared <strong>{{ $file->title }}</strong> with
                <strong>{{ $recipient->maskedEmail() }}</strong>. To open it, confirm the one-time code we email to that address.
            </p>
        </div>

        @if (session('status'))
            <div class="alert alert-ok">{{ session('status') }}</div>
        @endif

        @error('code')
            <div class="alert alert-error">{{ $message }}</div>
        @enderror

        @if ($codeSent || $errors->has('code'))
            <form method="POST" action="{{ route('fileshare.verify', $token) }}" class="stack">
                @csrf
                <div>
                    <label for="code">6-digit code</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="6" pattern="[0-9]{6}" class="code-input" required autofocus>
                    <p class="muted" style="margin:6px 0 0">The code expires in {{ config('fileshare.otp_ttl_minutes') }} minutes.</p>
                </div>
                <button type="submit" class="btn btn-block">Verify &amp; open file</button>
            </form>

            <form method="POST" action="{{ route('fileshare.send-code', $token) }}" class="row" style="justify-content:center">
                @csrf
                <span class="muted">Didn't get it?</span>
                <button type="submit" class="btn-link">Send a new code</button>
            </form>
        @else
            <form method="POST" action="{{ route('fileshare.send-code', $token) }}">
                @csrf
                <button type="submit" class="btn btn-block">Email me a code</button>
            </form>
        @endif
    </div>
@endsection
