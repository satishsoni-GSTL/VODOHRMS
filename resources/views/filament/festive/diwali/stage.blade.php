{{-- Decorative night sky behind the auth card. Sits outside the Livewire component, so form updates never touch it. --}}
<div class="dw-stage" data-dw-stage aria-hidden="true">
    <canvas class="dw-stars"></canvas>

    <svg class="dw-defs" width="0" height="0">
        <defs>
            <linearGradient id="dw-lantern-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#FFE29A" />
                <stop offset=".55" stop-color="#FF9F1C" />
                <stop offset="1" stop-color="#E8430E" />
            </linearGradient>
            <symbol id="dw-rangoli-art" viewBox="-200 -200 400 400">
                @foreach (range(0, 15) as $i)
                    <path transform="rotate({{ $i * 22.5 }})" d="M0-190C22-160 22-130 0-104-22-130-22-160 0-190Z" fill="{{ $i % 2 ? '#FF9F1C' : '#E8246F' }}" />
                    <circle transform="rotate({{ $i * 22.5 + 11.25 }})" cy="-168" r="6" fill="#FFD166" />
                @endforeach
                <circle r="102" fill="none" stroke="#FFD166" stroke-width="3" stroke-dasharray="3 9" stroke-linecap="round" />
                @foreach (range(0, 11) as $i)
                    <path transform="rotate({{ $i * 30 }})" d="M0-96C20-70 20-48 0-28-20-48-20-70 0-96Z" fill="{{ $i % 2 ? '#2EC4B6' : '#FFD166' }}" />
                @endforeach
                <circle r="24" fill="#E8246F" />
                <circle r="12" fill="#FFD166" />
            </symbol>
        </defs>
    </svg>

    <svg class="dw-rangoli dw-rangoli-left"><use href="#dw-rangoli-art" /></svg>
    <svg class="dw-rangoli dw-rangoli-right"><use href="#dw-rangoli-art" /></svg>

    @foreach ([[6, 0, 22, 1], [17, -9, 16, .75], [29, -4, 19, .9], [71, -13, 15, .7], [83, -6, 24, 1], [93, -17, 17, .8]] as [$left, $delay, $duration, $scale])
        <svg class="dw-lantern" viewBox="0 0 60 84" style="--left: {{ $left }}%; --delay: {{ $delay }}s; --duration: {{ $duration }}s; --scale: {{ $scale }}">
            <path d="M12 8h36l8 50c1 8-9 14-26 14S3 66 4 58L12 8Z" fill="url(#dw-lantern-fill)" />
            <path d="M12 8h36l1 6H11l1-6Z" fill="#B4231F" />
            <path d="M22 72h16l-2 8H24l-2-8Z" fill="#B4231F" />
            <ellipse cx="30" cy="62" rx="9" ry="5" fill="#FFF3C4" />
        </svg>
    @endforeach

    <canvas class="dw-sky"></canvas>

    <svg class="dw-toran" width="100%" height="84">
        <defs>
            <pattern id="dw-toran-tile" width="160" height="84" patternUnits="userSpaceOnUse">
                <path d="M80 42v22" stroke="#7A4A16" stroke-width="1.5" />
                <path d="M80 60c9 6 9 14 0 22-9-8-9-16 0-22Z" fill="#3E9B4F" />
                @foreach ([[0, 4], [20, 19], [40, 30], [60, 36], [80, 38], [100, 36], [120, 30], [140, 19], [160, 4]] as $index => [$x, $y])
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="10" fill="{{ $index % 2 ? '#FFB703' : '#FB7A0E' }}" />
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="5.5" fill="none" stroke="#C2410C" stroke-opacity=".55" stroke-width="1.5" stroke-dasharray="2 2" />
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="2.2" fill="#8A2C0B" />
                @endforeach
            </pattern>
        </defs>
        <rect width="100%" height="84" fill="url(#dw-toran-tile)" />
    </svg>

    <div class="dw-diyas">
        @foreach (range(1, 9) as $i)
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
