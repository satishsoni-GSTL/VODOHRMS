{{-- Decorative backdrop behind the sign-in card; outside the Livewire component so form updates never touch it. --}}
<div class="fx-stage fx-theme-{{ $theme['key'] }}" data-fx-stage data-fx-effect="{{ $theme['effect'] }}" data-fx-colors='@json($theme['accents'])' aria-hidden="true">
    <canvas class="fx-canvas"></canvas>
    <div class="fx-stage-art fx-stage-art-left">@include('filament.festive.generic.motif', ['motif' => $theme['motif'], 'theme' => $theme])</div>
    <div class="fx-stage-art fx-stage-art-right">@include('filament.festive.generic.motif', ['motif' => $theme['motif'], 'theme' => $theme])</div>
    @if ($theme['effect'] === 'tricolour')
        <div class="fx-tricolour-band"></div>
    @endif
</div>
