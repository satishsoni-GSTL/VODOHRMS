<div class="dw-days-block">
    <p class="dw-countdown">{{ \App\Support\DiwaliTheme::countdown() }}</p>

    <ol class="dw-days">
        @foreach (\App\Support\DiwaliTheme::days() as $name => $date)
            <li @class([
                'dw-day',
                'is-main' => $name === 'Diwali',
                'is-today' => $date->isToday(),
                'is-past' => $date->isPast() && ! $date->isToday(),
            ])>
                <span class="dw-day-date">{{ $date->format('j M') }}</span>
                <span class="dw-day-name">{{ $name }}</span>
            </li>
        @endforeach
    </ol>
</div>
