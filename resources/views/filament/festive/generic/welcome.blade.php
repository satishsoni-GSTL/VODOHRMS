<div class="fx-banner fx-theme-{{ $theme['key'] }}" data-fx-welcome="{{ $theme['key'] }}" data-fx-effect="{{ $theme['effect'] }}" data-fx-colors='@json($theme['accents'])'>
    <div class="fx-banner-copy">
        @if ($theme['preview'])
            <span class="fx-preview-tag">Preview — only you can see this ·
                <a href="{{ request()->url() }}?festive_preview=off">End preview</a></span>
        @endif
        @if ($theme['local'])
            <p class="fx-local" lang="hi">{{ $theme['local'] }}</p>
        @endif
        <p class="fx-banner-title">{{ $theme['title'] }}</p>
        <p class="fx-banner-text">{{ $theme['message'] }}</p>

        @include('filament.festive.generic.days')
    </div>

    <div class="fx-banner-art" aria-hidden="true">
        @include('filament.festive.generic.motif', ['motif' => $theme['motif'], 'theme' => $theme])
    </div>
</div>
