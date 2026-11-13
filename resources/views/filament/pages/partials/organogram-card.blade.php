<div @class(['org-card', $toneClass, 'is-match' => $node['match'] ?? false, 'is-me' => $node['isMe'] ?? false])
     style="--stripe: {{ ($node['vacant'] ?? false) ? 'var(--org-danger)' : $node['color'] }}">
    <span class="org-toggle" aria-hidden="true">{{ $hasChildren ? '▶' : '' }}</span>
    <span class="org-avatar" style="background: {{ ($node['vacant'] ?? false) ? '#9ca3af' : $node['color'] }}">
        @if ($node['avatar'])
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($node['avatar']) }}" alt="" loading="lazy">
        @else
            {{ $node['initials'] }}
        @endif
    </span>
    <span class="org-body">
        <span class="org-title block">{{ $node['title'] }}@if ($node['isMe'] ?? false) <span style="color: var(--org-me)">(You)</span>@endif</span>
        @if ($node['subtitle'])<span class="org-sub block">{{ $node['subtitle'] }}</span>@endif
        @if ($node['meta'])<span class="org-meta block">{{ $node['meta'] }}</span>@endif
    </span>
    <span class="org-side">
        @if ($node['badge'])<x-filament::badge :color="$node['badgeColor']" size="sm">{{ $node['badge'] }}</x-filament::badge>@endif
        @if ($node['note'])<span class="org-chip {{ $node['tone'] === 'danger' ? 'c-danger' : ($node['tone'] === 'warning' ? 'c-warn' : '') }}">{{ $node['note'] }}</span>@endif
        @if ($node['descendants'] > 0)<span class="org-chip">{{ $node['descendants'] }} below</span>@endif
        @if (($node['vacancies'] ?? 0) > 0 && ! ($node['vacant'] ?? false))<span class="org-chip c-danger">{{ $node['vacancies'] }} vacant below</span>@endif
        @if ($hasChildren)
            <button type="button" class="org-chip" title="Show only this branch" wire:click.prevent="focusOn('{{ $node['key'] }}')">Focus</button>
        @endif
        @if ($node['url'] ?? null)<a href="{{ $node['url'] }}" class="org-chip" title="Open employee record">Open ↗</a>@endif
    </span>
</div>
