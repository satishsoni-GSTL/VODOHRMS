{{-- Theme artwork, inline SVG (no image files). $motif, $theme --}}
@switch($motif)
    @case('new_year')
        <svg viewBox="0 0 200 120" class="fx-motif" aria-hidden="true">
            <defs>
                <linearGradient id="fx-gold" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#fff7c2" /><stop offset=".5" stop-color="#facc15" /><stop offset="1" stop-color="#b45309" />
                </linearGradient>
            </defs>
            <text x="100" y="82" text-anchor="middle" font-size="64" font-weight="800" font-family="Georgia, 'Times New Roman', serif" fill="url(#fx-gold)" letter-spacing="2">{{ $theme['days'] !== [] ? end($theme['days'])['date']->year : now()->addMonth()->year }}</text>
            @foreach ([[22, 22, 7], [178, 28, 9], [160, 100, 6], [36, 104, 5], [100, 14, 6]] as [$x, $y, $r])
                <path class="fx-twinkle" style="--d: {{ $loop->index * .4 }}s" transform="translate({{ $x }} {{ $y }})" d="M0-{{ $r }}L{{ $r * .25 }}-{{ $r * .25 }}L{{ $r }} 0L{{ $r * .25 }} {{ $r * .25 }}L0 {{ $r }}L-{{ $r * .25 }} {{ $r * .25 }}L-{{ $r }} 0L-{{ $r * .25 }}-{{ $r * .25 }}Z" fill="#fde68a" />
            @endforeach
        </svg>
        @break

    @case('christmas')
        <svg viewBox="0 0 120 140" class="fx-motif" aria-hidden="true">
            <path d="M60 18 92 62H76l26 34H82l28 32H10l28-32H18l26-34H28Z" fill="#166534" />
            <path d="M60 18 92 62H76l26 34H82l28 32H60Z" fill="#14532d" />
            <rect x="52" y="128" width="16" height="10" rx="2" fill="#78350f" />
            <path class="fx-twinkle" d="M60 4l3.5 7.5 8 1-6 5.5 1.5 8L60 22l-7 4 1.5-8-6-5.5 8-1Z" fill="#facc15" />
            @foreach ([[48, 56, '#ef4444'], [72, 74, '#facc15'], [44, 92, '#60a5fa'], [80, 108, '#ef4444'], [38, 118, '#facc15'], [64, 100, '#f472b6']] as [$x, $y, $c])
                <circle class="fx-twinkle" style="--d: {{ $loop->index * .3 }}s" cx="{{ $x }}" cy="{{ $y }}" r="4.5" fill="{{ $c }}" />
            @endforeach
        </svg>
        @break

    @case('trophy')
        <svg viewBox="0 0 120 130" class="fx-motif" aria-hidden="true">
            <defs>
                <linearGradient id="fx-trophy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#fef08a" /><stop offset=".55" stop-color="#eab308" /><stop offset="1" stop-color="#a16207" />
                </linearGradient>
            </defs>
            <path d="M30 14h60v26c0 18-13 32-30 32S30 58 30 40Z" fill="url(#fx-trophy)" />
            <path d="M30 22H14c0 18 8 28 20 30M90 22h16c0 18-8 28-20 30" fill="none" stroke="#eab308" stroke-width="6" stroke-linecap="round" />
            <rect x="53" y="70" width="14" height="22" fill="#ca8a04" />
            <rect x="36" y="92" width="48" height="12" rx="3" fill="#a16207" />
            <rect x="30" y="104" width="60" height="14" rx="3" fill="#713f12" />
            <path class="fx-twinkle" d="M60 26l4 8 9 1.3-6.5 6.3 1.5 9L60 46.4 52 50.6l1.5-9L47 35.3l9-1.3Z" fill="#fff7c2" />
        </svg>
        @break

    @case('cake')
        <svg viewBox="0 0 120 130" class="fx-motif" aria-hidden="true">
            @foreach ([38, 60, 82] as $x)
                <rect x="{{ $x - 3 }}" y="30" width="6" height="22" rx="2" fill="{{ ['#60a5fa', '#f472b6', '#34d399'][$loop->index] }}" />
                <path class="fx-flame" style="--d: {{ $loop->index * .2 }}s" d="M{{ $x }} 14c5 7 7 10 7 13a7 7 0 0 1-14 0c0-3 2-6 7-13Z" fill="#fb923c" />
            @endforeach
            <rect x="18" y="52" width="84" height="30" rx="8" fill="#fbcfe8" />
            <path d="M18 62c7 8 14 8 21 0s14-8 21 0 14 8 21 0 14-8 21 0" fill="none" stroke="#f9a8d4" stroke-width="6" />
            <rect x="10" y="80" width="100" height="36" rx="9" fill="#db2777" />
            <path d="M10 92c8 9 17 9 25 0s17-9 25 0 17 9 25 0 17-9 25 0" fill="none" stroke="#fdf2f8" stroke-width="5" stroke-opacity=".7" />
            <rect x="4" y="114" width="112" height="8" rx="4" fill="#9d174d" />
        </svg>
        @break

    @case('holi')
        <svg viewBox="0 0 140 120" class="fx-motif" aria-hidden="true">
            @foreach ([[44, 46, 32, '#ec4899'], [92, 40, 30, '#facc15'], [70, 78, 34, '#22c55e'], [30, 88, 22, '#3b82f6'], [112, 86, 24, '#a855f7']] as [$x, $y, $r, $c])
                <circle class="fx-pulse" style="--d: {{ $loop->index * .35 }}s" cx="{{ $x }}" cy="{{ $y }}" r="{{ $r }}" fill="{{ $c }}" fill-opacity=".78" />
            @endforeach
            @foreach (range(0, 17) as $i)
                <circle cx="{{ 70 + cos($i * 0.35) * (50 + ($i % 3) * 8) }}" cy="{{ 60 + sin($i * 0.35) * (44 + ($i % 4) * 5) }}" r="{{ 2 + $i % 3 }}" fill="{{ $theme['accents'][$i % count($theme['accents'])] }}" />
            @endforeach
        </svg>
        @break

    @case('flag')
        <svg viewBox="0 0 150 110" class="fx-motif" aria-hidden="true">
            <rect x="12" y="6" width="5" height="102" rx="2" fill="#78716c" />
            <g class="fx-wave">
                <rect x="17" y="10" width="120" height="26" fill="#ff9933" />
                <rect x="17" y="36" width="120" height="26" fill="#ffffff" />
                <rect x="17" y="62" width="120" height="26" fill="#138808" />
                <g transform="translate(77 49)">
                    <circle r="11" fill="none" stroke="#000080" stroke-width="1.6" />
                    <circle r="2" fill="#000080" />
                    @foreach (range(0, 23) as $i)
                        <line x1="0" y1="0" x2="0" y2="-11" stroke="#000080" stroke-width=".7" transform="rotate({{ $i * 15 }})" />
                    @endforeach
                </g>
            </g>
        </svg>
        @break

    @default
        <svg viewBox="0 0 80 80" class="fx-motif" aria-hidden="true">
            <path d="M40 8c8 12 12 18 12 25a12 12 0 0 1-24 0c0-7 4-13 12-25Z" fill="#FF9F1C" />
            <path d="M8 44h64c0 15-14 26-32 26S8 59 8 44Z" fill="#B4531F" />
        </svg>
@endswitch
