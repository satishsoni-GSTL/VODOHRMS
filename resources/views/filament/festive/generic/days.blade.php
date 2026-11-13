@if ($theme['countdown'] || $theme['days'] !== [])
    <div class="fx-days-block">
        @if ($theme['countdown'])
            <p class="fx-countdown">{{ $theme['countdown'] }}</p>
        @endif
        @if (count($theme['days']) > 1)
            <ol class="fx-days" style="--n: {{ min(count($theme['days']), 5) }}">
                @foreach ($theme['days'] as $day)
                    <li @class(['fx-day', 'is-today' => $day['date']->isToday(), 'is-past' => $day['date']->isPast() && ! $day['date']->isToday()])>
                        <span class="fx-day-date">{{ $day['date']->format('j M') }}</span>
                        <span class="fx-day-name">{{ $day['name'] }}</span>
                    </li>
                @endforeach
            </ol>
        @elseif (count($theme['days']) === 1 && ! $theme['days'][0]['date']->isPast())
            <p class="fx-day-single">{{ $theme['days'][0]['date']->format('l, j F Y') }}@if ($theme['venue']) · {{ $theme['venue'] }}@endif</p>
        @endif
    </div>
@endif
