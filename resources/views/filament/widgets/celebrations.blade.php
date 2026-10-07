@php
    $items = $this->items();
    $today = $items->where('is_today', true);
    $upcoming = $items->where('is_today', false);
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Birthdays &amp; Work Anniversaries</x-slot>
        <x-slot name="description">Across the company · today and the next {{ \App\Filament\Widgets\Celebrations::DAYS_AHEAD }} days</x-slot>

        @if ($items->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No birthdays or work anniversaries this week.</p>
        @else
            @if ($today->isNotEmpty())
                <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($today as $c)
                        {{-- Inline colours: the panel has no custom Tailwind build (GlobalSpace orange / teal). --}}
                        <div class="flex items-center gap-3 rounded-xl p-3"
                             style="{{ $c['type'] === 'birthday'
                                 ? 'background-color: rgba(241,137,44,.10); border: 1px solid rgba(241,137,44,.35);'
                                 : 'background-color: rgba(12,132,129,.08); border: 1px solid rgba(12,132,129,.30);' }}">
                            <div class="text-2xl" aria-hidden="true">{{ $c['type'] === 'birthday' ? '🎂' : '🎉' }}</div>
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-gray-950 dark:text-white">
                                    {{ trim(preg_replace('/\s+/', ' ', $c['employee']->full_name)) }}
                                    @if ($c['is_me']) <span class="text-xs font-normal text-gray-500">(you)</span> @endif
                                </div>
                                <div class="truncate text-xs text-gray-600 dark:text-gray-400">
                                    {{ $c['type'] === 'birthday' ? 'Birthday today' : $c['years'].'-year work anniversary today' }}
                                    · {{ $c['employee']->designation?->name ?? $c['employee']->employee_code }}
                                    @if ($c['employee']->department) · {{ $c['employee']->department->name }} @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($upcoming->isNotEmpty())
                <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Coming up</div>
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($upcoming as $c)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div class="flex min-w-0 items-center gap-2">
                                <span aria-hidden="true">{{ $c['type'] === 'birthday' ? '🎂' : '🎉' }}</span>
                                <span class="truncate font-medium text-gray-950 dark:text-white">{{ trim(preg_replace('/\s+/', ' ', $c['employee']->full_name)) }}</span>
                                <span class="hidden truncate text-gray-500 sm:inline dark:text-gray-400">
                                    · {{ $c['type'] === 'birthday' ? 'Birthday' : $c['years'].'-year anniversary' }}
                                    @if ($c['employee']->department) · {{ $c['employee']->department->name }} @endif
                                </span>
                            </div>
                            <span class="shrink-0 text-gray-500 dark:text-gray-400">
                                {{ \Carbon\Carbon::parse($c['date'])->format('D, d M') }}
                                @if ($c['days_away'] === 1) <span class="text-xs">(tomorrow)</span> @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
