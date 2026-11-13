@php
    $hasChildren = $node['children'] !== [];
    $toneClass = match ($node['tone']) { 'danger' => 'tone-danger', 'warning' => 'tone-warning', default => '' };
@endphp
<li>
    @if ($hasChildren)
        <details @if ($forceOpen || $depth < $openDepth) open @endif>
            <summary>@include('filament.pages.partials.organogram-card')</summary>
            <ul>
                @foreach ($node['children'] as $child)
                    @include('filament.pages.partials.organogram-node', ['node' => $child, 'depth' => $depth + 1])
                @endforeach
            </ul>
        </details>
    @else
        @include('filament.pages.partials.organogram-card')
    @endif
</li>
