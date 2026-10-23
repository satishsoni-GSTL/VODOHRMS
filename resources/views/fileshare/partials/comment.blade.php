<div class="comment" id="comment-{{ $comment->id }}">
    <div class="avatar" aria-hidden="true">{{ $initials($comment->author_name) }}</div>
    <div class="comment-body">
        <div class="comment-head">
            <strong>{{ $comment->author_name }}</strong>
            @if ($comment->user_id === $file->owner_id && $comment->recipient_id === null)
                <span class="badge">Owner</span>
            @endif
            <span class="muted" title="{{ $comment->created_at->format('d M Y, h:i A') }}">{{ $comment->created_at->diffForHumans() }}</span>
        </div>
        <div class="comment-text">{{ $comment->body }}</div>
    </div>
</div>
