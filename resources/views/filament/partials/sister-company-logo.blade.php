{{-- Inline styles: compiled Filament CSS only has the classes it uses. Same height as the brand logo; the white plate keeps it readable in dark mode. --}}
<img
    src="{{ asset('images/makebot-logo.png') }}"
    alt="Makebot Robotics"
    style="box-sizing: content-box; height: {{ filament()->getBrandLogoHeight() }}; width: auto; padding: 0.125rem 0.375rem; border-radius: 0.375rem; background: #fff;"
/>
