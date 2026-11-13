@if ($theme['local'])
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=tiro-devanagari-hindi:400&display=swap" />
@endif
<link rel="stylesheet" href="{{ asset('css/festive/festive.css') }}?v={{ filemtime(public_path('css/festive/festive.css')) }}" />
<style>
    :root {
        --fx-stage-1: {{ $theme['stage'][0] }}; --fx-stage-2: {{ $theme['stage'][1] }}; --fx-stage-3: {{ $theme['stage'][2] }};
        --fx-banner-1: {{ $theme['banner'][0] }}; --fx-banner-2: {{ $theme['banner'][1] }}; --fx-banner-3: {{ $theme['banner'][2] }};
        --fx-primary: {{ $theme['primary'] }};
        --fx-accent: {{ $theme['accents'][0] }};
        --fx-accent-2: {{ $theme['accents'][1] ?? $theme['accents'][0] }};
    }
</style>
<script src="{{ asset('js/festive/festive.js') }}?v={{ filemtime(public_path('js/festive/festive.js')) }}" defer></script>
