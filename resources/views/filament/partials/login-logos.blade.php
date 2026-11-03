<div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 1rem;">
    <img src="{{ filament()->getBrandLogo() }}" alt="{{ filament()->getBrandName() }}" class="flex dark:hidden" style="height: {{ filament()->getBrandLogoHeight() }};" />
    <img src="{{ filament()->getDarkModeBrandLogo() }}" alt="{{ filament()->getBrandName() }}" class="hidden dark:flex" style="height: {{ filament()->getBrandLogoHeight() }};" />

    @include('filament.partials.sister-company-logo')
</div>
