@extends('fileshare.layout')

@section('title', $file->title)

@section('topbar-actions')
    <form method="POST" action="{{ route('fileshare.sign-out', $token) }}" class="row">
        @csrf
        <span class="muted">{{ $recipient->email }}</span>
        <button type="submit" class="btn btn-light">Sign out</button>
    </form>
@endsection

@php
    $initials = fn (string $name) => strtoupper(mb_substr(trim($name), 0, 1));
@endphp

@section('content')
    @if (session('status'))
        <div class="alert alert-ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="row space-between" style="align-items:flex-start">
            <div style="min-width:0">
                <h1>{{ $file->title }}</h1>
                <div class="meta muted">
                    <span>{{ $file->file_name }}</span>
                    <span>{{ $file->humanSize() }}</span>
                    <span>Shared by {{ $file->owner->name }}</span>
                    <span>{{ $file->created_at->format('d M Y') }}</span>
                    @if ($file->expires_at)
                        <span>Available until {{ $file->expires_at->format('d M Y, h:i A') }}</span>
                    @endif
                </div>
            </div>
            @if ($file->allow_download)
                <a href="{{ route('fileshare.download', $token) }}" class="btn">Download</a>
            @endif
        </div>

        @if ($file->description)
            <p style="white-space:pre-wrap;margin:16px 0 0">{{ $file->description }}</p>
        @endif

        <div class="preview">
            @switch($file->previewKind())
                @case('pdf')
                @case('text')
                    <iframe src="{{ route('fileshare.preview', $token) }}" title="{{ $file->file_name }}"></iframe>
                    @break
                @case('image')
                    <img src="{{ route('fileshare.preview', $token) }}" alt="{{ $file->title }}">
                    @break
                @case('video')
                    <video src="{{ route('fileshare.preview', $token) }}" controls></video>
                    @break
                @case('audio')
                    <audio src="{{ route('fileshare.preview', $token) }}" controls></audio>
                    @break
                @default
                    <div class="no-preview">
                        Preview isn't available for this file type.
                        @if ($file->allow_download)
                            <br><a href="{{ route('fileshare.download', $token) }}">Download it</a> to open it on your device.
                        @else
                            <br>The owner has turned off downloads for this file.
                        @endif
                    </div>
            @endswitch
        </div>
    </section>

    <section class="card" id="comments">
        <h2>Comments ({{ $file->rootComments->sum(fn ($c) => 1 + $c->replies->count()) }})</h2>

        @forelse ($file->rootComments as $thread)
            <div class="thread">
                @include('fileshare.partials.comment', ['comment' => $thread, 'initials' => $initials, 'file' => $file])

                @if ($thread->replies->isNotEmpty())
                    <div class="replies">
                        @foreach ($thread->replies as $reply)
                            @include('fileshare.partials.comment', ['comment' => $reply, 'initials' => $initials, 'file' => $file])
                        @endforeach
                    </div>
                @endif

                @if ($recipient->can_comment)
                    <details class="reply-form" @if (old('parent_id') == $thread->id) open @endif>
                        <summary class="btn-link">Reply</summary>
                        <form method="POST" action="{{ route('fileshare.comment', $token) }}" class="stack" style="margin-top:10px">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $thread->id }}">
                            <textarea name="body" rows="2" maxlength="5000" required placeholder="Write a reply…">{{ old('parent_id') == $thread->id ? old('body') : '' }}</textarea>
                            <div><button type="submit" class="btn">Post reply</button></div>
                        </form>
                    </details>
                @endif
            </div>
        @empty
            <p class="muted">No comments yet.@if ($recipient->can_comment) Start the conversation below.@endif</p>
        @endforelse

        @if ($recipient->can_comment)
            <form method="POST" action="{{ route('fileshare.comment', $token) }}" class="stack" style="margin-top:20px;border-top:1px solid var(--border);padding-top:20px">
                @csrf
                <div>
                    <label for="new-comment">Add a comment</label>
                    <textarea id="new-comment" name="body" rows="3" maxlength="5000" required placeholder="Ask a question or leave feedback for {{ $file->owner->name }}…">{{ old('parent_id') ? '' : old('body') }}</textarea>
                </div>
                <div><button type="submit" class="btn">Post comment</button></div>
            </form>
        @endif
    </section>
@endsection
