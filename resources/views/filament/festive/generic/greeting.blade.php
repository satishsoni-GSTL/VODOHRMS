<div class="fx-greeting">
    @if ($theme['local'])
        <p class="fx-local" lang="hi">{{ $theme['local'] }}</p>
    @endif
    <p class="fx-greeting-title">{{ $theme['title'] }}</p>
    <p class="fx-greeting-text">{{ \Illuminate\Support\Str::limit($theme['message'], 110) }}</p>

    @include('filament.festive.generic.days')
</div>
