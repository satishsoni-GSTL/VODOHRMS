<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $complete = $completion['percent'] >= 100;
            // Inline colours: the panel has no custom Tailwind build; Filament exposes its palette as CSS variables.
            $accent = $complete ? '--success' : '--warning';
            $bar = $complete ? '--success-500' : '--primary-500';
        @endphp

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
                <div style="width:64px;height:64px;flex-shrink:0;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;
                            background:rgba(var({{ $accent }}-500), .12);color:rgb(var({{ $accent }}-600))">
                    {{ $completion['percent'] }}%
                </div>
                <div>
                    <h2 class="text-base font-semibold">{{ $complete ? 'Your profile is complete' : 'Complete your profile' }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if ($complete)
                            All your details and documents are on file. Thank you!
                        @else
                            {{ $completion['total'] - $completion['done'] }} item(s) missing. HR needs these for payroll, statutory filings and emergencies.
                        @endif
                    </p>
                </div>
            </div>
            <x-filament::button tag="a" :href="$url" :color="$complete ? 'gray' : 'primary'" icon="heroicon-o-pencil-square">
                {{ $complete ? 'View profile' : 'Fill missing details' }}
            </x-filament::button>
        </div>

        <div style="margin-top:16px;height:8px;width:100%;overflow:hidden;border-radius:9999px;background:rgba(var(--gray-400), .25)">
            <div style="height:8px;border-radius:9999px;width:{{ $completion['percent'] }}%;background:rgb(var({{ $bar }}))"></div>
        </div>

        @unless ($complete)
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4" style="margin-top:16px">
                @foreach ($completion['sections'] as $key => $section)
                    @php $done = $section['done'] === $section['total']; @endphp
                    <a href="{{ $url }}#{{ $key }}" class="rounded-lg text-sm" style="display:block;padding:10px;border:1px solid rgba(var(--gray-400), .35)">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ $section['label'] }}</span>
                            <span style="font-size:12px;font-weight:600;color:rgb(var({{ $done ? '--success-600' : '--warning-600' }}))">
                                {{ $done ? '✓' : $section['done'].'/'.$section['total'] }}
                            </span>
                        </div>
                        @unless ($done)
                            <div class="text-xs text-gray-500 dark:text-gray-400" style="margin-top:2px">
                                {{ collect($section['items'])->where('done', false)->pluck('label')->implode(', ') }}
                            </div>
                        @endunless
                    </a>
                @endforeach
            </div>
        @endunless
    </x-filament::section>
</x-filament-widgets::widget>
