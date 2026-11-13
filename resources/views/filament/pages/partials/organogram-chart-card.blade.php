@php
    $toneClass = match ($node['tone']) { 'danger' => 'tone-danger', 'warning' => 'tone-warning', default => '' };
    $tooltip = collect([$node['title'], $node['subtitle'], $node['meta'], $node['group'] ?? null])->filter()->implode("\n");
@endphp
<div @class(['oc-card', $toneClass, 'is-me' => $node['isMe'] ?? false, 'is-match' => $node['match'] ?? false]) title="{{ $tooltip }}">
    <div class="oc-stripe" style="background: {{ ($node['vacant'] ?? false) ? 'var(--org-danger)' : $node['color'] }}"></div>
    <div class="oc-main">
        <span class="oc-avatar" style="background: {{ ($node['vacant'] ?? false) ? '#9ca3af' : $node['color'] }}">
            @if ($node['avatar'])
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($node['avatar']) }}" alt="" loading="lazy">
            @else
                {{ $node['initials'] }}
            @endif
        </span>
        <span class="oc-text">
            <span class="oc-name">{{ $node['title'] }}@if ($node['isMe'] ?? false) <span style="color: var(--org-me); font-weight: 600">(You)</span>@endif</span>
            @if ($node['subtitle'])<span class="oc-role">{{ $node['subtitle'] }}</span>@endif
            @if ($node['meta'])<span class="oc-meta">{{ $node['meta'] }}</span>@endif
        </span>
    </div>
    <div class="oc-foot">
        @if ($node['badge'])<x-filament::badge :color="$node['badgeColor']" size="sm">{{ $node['badge'] }}</x-filament::badge>@endif
        @if ($node['note'])<span class="org-chip {{ $node['tone'] === 'danger' ? 'c-danger' : ($node['tone'] === 'warning' ? 'c-warn' : '') }}">{{ $node['note'] }}</span>@endif
        @if ($node['descendants'] > 0)<span class="org-chip" title="Everyone below">{{ $node['descendants'] }} in team</span>@endif
        @if (($node['vacancies'] ?? 0) > 0 && ! ($node['vacant'] ?? false))<span class="org-chip c-danger">{{ $node['vacancies'] }} vacant</span>@endif
    </div>
    <div class="oc-tools org-noprint">
        @if ($node['children'] !== [])
            <button type="button" title="Show only this branch" wire:click="focusOn('{{ $node['key'] }}')">◎</button>
        @endif
        @if ($node['url'] ?? null)
            <a href="{{ $node['url'] }}" title="Open employee record">↗</a>
        @endif
    </div>
</div>
