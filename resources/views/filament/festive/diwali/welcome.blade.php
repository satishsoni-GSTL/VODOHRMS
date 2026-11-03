@php
    $firstName = \Illuminate\Support\Str::of(filament()->getUserName(auth()->user()))->trim()->before(' ');
@endphp

<div class="dw-banner" data-dw-welcome>
    <div class="dw-banner-copy">
        <p class="dw-banner-title">
            <span lang="hi">शुभ दीपावली</span>, {{ $firstName }}
        </p>
        <p class="dw-banner-text">
            May this festival of lights bring you and your family joy, good health and prosperity.
            With warm wishes from Team GlobalSpace.
        </p>

        @include('filament.festive.diwali.days')
    </div>

    <div class="dw-banner-diyas" aria-hidden="true">
        @foreach (range(1, 3) as $i)
            <svg class="dw-diya" viewBox="0 0 80 80" style="--i: {{ $i }}">
                <g class="dw-flame">
                    <path d="M40 8c8 12 12 18 12 25a12 12 0 0 1-24 0c0-7 4-13 12-25Z" fill="#FF9F1C" />
                    <path d="M40 20c4 7 6 10 6 14a6 6 0 0 1-12 0c0-4 2-7 6-14Z" fill="#FFE66D" />
                </g>
                <path d="M8 44h64c0 15-14 26-32 26S8 59 8 44Z" fill="#B4531F" />
                <path d="M8 44h64c-1 4-3 7-6 10H14c-3-3-5-6-6-10Z" fill="#E07A2F" />
            </svg>
        @endforeach
    </div>
</div>
