@php
    $hasChildren = $node['children'] !== [];
    $startOpen = $forceOpen || $depth < $openDepth;
@endphp
<li x-data="{ open: @js($startOpen) }">
    @include('filament.pages.partials.organogram-chart-card')

    @if ($hasChildren)
        <button type="button" class="oc-toggle org-noprint" x-on:click="open = ! open"
                x-bind:title="open ? 'Hide team' : 'Show team'"
                x-text="open ? '−' : '+{{ $node['direct'] }}'">{{ $startOpen ? '−' : '+'.$node['direct'] }}</button>

        <ul @class(['oc-stack' => $node['stackChildren']]) x-show="open" @unless ($startOpen) style="display:none" @endunless>
            @foreach ($node['children'] as $child)
                @include('filament.pages.partials.organogram-chart-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
