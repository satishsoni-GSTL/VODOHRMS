<?php

namespace App\Support;

use App\Models\FestiveTheme;
use Filament\Support\Colors\Color;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Celebration themes for the portal (login page, dashboard banner, panel colours).
 *
 * The catalogue below is fixed in code (look, colours, effect); what HR controls from
 * Organization → Celebration Themes (table `festive_themes`) is whether each one is
 * switched on, its date window, greeting text and countdown days.
 *
 * Which theme shows, per request:
 *   1. an admin preview (?festive_preview=key, kept in the session),
 *   2. the personal Birthday theme, on the signed-in employee's own birthday,
 *   3. the switched-on theme whose date window covers today (or that has no dates).
 *
 * Diwali keeps its own hand-made artwork (filament/festive/diwali); every other theme uses
 * the shared engine (filament/festive/generic + public/css|js/festive/festive.*).
 */
class FestiveThemes
{
    public const SESSION_PREVIEW = 'festive_preview';

    private const MEMO = 'hrms.festive.current';

    /**
     * @var array<string, array{name: string, description: string, personal?: bool, effect: string, motif: string,
     *     colors: array<string, string>, gray?: string, stage: array<int, string>, banner: array<int, string>, accents: array<int, string>,
     *     local?: string, title: string, message: string}>
     */
    public const DEFINITIONS = [
        'diwali' => [
            'name' => 'Diwali',
            'description' => 'Festival of lights: diyas, rangoli, lanterns and fireworks.',
            'effect' => 'fireworks', 'motif' => 'diya',
            'colors' => ['primary' => '#B0125B', 'warning' => '#F59E0B'], 'gray' => 'stone',
            'stage' => ['#14061f', '#2a0b2e', '#4a1033'], 'banner' => ['#2a0b2e', '#6d0f43', '#b4380f'],
            'accents' => ['#ffc857', '#ff5d8f', '#ff8c1a', '#fff1c2', '#2ec4b6'],
            'local' => 'शुभ दीपावली',
            'title' => 'Happy Diwali, {name}',
            'message' => 'May this festival of lights bring you and your family joy, good health and prosperity.',
        ],
        'new_year' => [
            'name' => 'New Year',
            'description' => 'Midnight blue and gold with fireworks and a countdown to 1 January.',
            'effect' => 'fireworks', 'motif' => 'new_year',
            'colors' => ['primary' => '#1D4ED8', 'warning' => '#EAB308'], 'gray' => 'slate',
            'stage' => ['#020617', '#0b1b4d', '#1e3a8a'], 'banner' => ['#020617', '#1e3a8a', '#6d28d9'],
            'accents' => ['#facc15', '#f8fafc', '#60a5fa', '#f472b6', '#a78bfa'],
            'title' => 'Happy New Year {year}, {name}!',
            'message' => 'Thank you for everything you did this year. Here’s to a healthy, happy and successful {year}.',
        ],
        'christmas' => [
            'name' => 'Christmas',
            'description' => 'Red and green with falling snow and a Christmas tree.',
            'effect' => 'snow', 'motif' => 'christmas',
            'colors' => ['primary' => '#B91C1C', 'warning' => '#CA8A04'], 'gray' => 'stone',
            'stage' => ['#04130c', '#0c3b26', '#7f1d1d'], 'banner' => ['#0c3b26', '#14532d', '#991b1b'],
            'accents' => ['#ffffff', '#e2e8f0', '#fde68a'],
            'title' => 'Merry Christmas, {name}!',
            'message' => 'Wishing you and your loved ones peace, joy and warmth this festive season.',
        ],
        'annual_day' => [
            'name' => 'Annual Day',
            'description' => 'Company celebration: purple and gold, confetti, trophy, event date and venue.',
            'effect' => 'confetti', 'motif' => 'trophy',
            'colors' => ['primary' => '#7C3AED', 'warning' => '#D97706'], 'gray' => 'zinc',
            'stage' => ['#12052b', '#3b0764', '#581c87'], 'banner' => ['#1e0a3c', '#5b21b6', '#b45309'],
            'accents' => ['#facc15', '#a78bfa', '#f472b6', '#38bdf8', '#ffffff'],
            'title' => '{company} Annual Day {year}',
            'message' => 'Join us to celebrate our year together{venue_text}. See you there, {name}!',
        ],
        'birthday' => [
            'name' => 'Birthday',
            'personal' => true,
            'description' => 'Personal: each employee sees balloons and a greeting on their own birthday.',
            'effect' => 'balloons', 'motif' => 'cake',
            'colors' => ['primary' => '#DB2777', 'warning' => '#F59E0B'], 'gray' => 'stone',
            'stage' => ['#3b0a2a', '#831843', '#be185d'], 'banner' => ['#831843', '#db2777', '#f59e0b'],
            'accents' => ['#f472b6', '#facc15', '#60a5fa', '#34d399', '#a78bfa', '#fb923c'],
            'title' => 'Happy Birthday, {name}! 🎉',
            'message' => 'Wishing you a wonderful year ahead. Have a great day — from all of us at {company}.',
        ],
        'holi' => [
            'name' => 'Holi',
            'description' => 'Festival of colours: bright powder bursts.',
            'effect' => 'colors', 'motif' => 'holi',
            'colors' => ['primary' => '#C026D3', 'warning' => '#F59E0B'], 'gray' => 'neutral',
            'stage' => ['#2e1065', '#86198f', '#be123c'], 'banner' => ['#7e22ce', '#db2777', '#f59e0b'],
            'accents' => ['#f43f5e', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#ec4899', '#facc15'],
            'local' => 'होली मुबारक',
            'title' => 'Happy Holi, {name}!',
            'message' => 'May your life be filled with the colours of joy, love and happiness.',
        ],
        'independence_day' => [
            'name' => 'Independence Day',
            'description' => 'Tricolour with the national flag (15 August).',
            'effect' => 'tricolour', 'motif' => 'flag',
            'colors' => ['primary' => '#EA580C', 'warning' => '#16A34A'], 'gray' => 'slate',
            'stage' => ['#0b1d3a', '#1e3a8a', '#0f172a'], 'banner' => ['#c2410c', '#1e3a8a', '#15803d'],
            'accents' => ['#ff9933', '#ffffff', '#138808'],
            'local' => 'जय हिन्द',
            'title' => 'Happy Independence Day, {name}!',
            'message' => 'Celebrating the spirit of freedom. Jai Hind!',
        ],
        'republic_day' => [
            'name' => 'Republic Day',
            'description' => 'Tricolour with the national flag (26 January).',
            'effect' => 'tricolour', 'motif' => 'flag',
            'colors' => ['primary' => '#EA580C', 'warning' => '#16A34A'], 'gray' => 'slate',
            'stage' => ['#0b1d3a', '#1e3a8a', '#0f172a'], 'banner' => ['#c2410c', '#1e3a8a', '#15803d'],
            'accents' => ['#ff9933', '#ffffff', '#138808'],
            'local' => 'गणतंत्र दिवस की शुभकामनाएँ',
            'title' => 'Happy Republic Day, {name}!',
            'message' => 'Honouring our Constitution and the values that unite us.',
        ],
    ];

    public static function definition(string $key): ?array
    {
        return self::DEFINITIONS[$key] ?? null;
    }

    /** @return array<string, string> key => name */
    public static function options(): array
    {
        return array_map(fn (array $d) => $d['name'], self::DEFINITIONS);
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::MEMO);
    }

    /**
     * The theme to show on this request, or null for the normal look.
     *
     * @return array{key: string, def: array, row: ?FestiveTheme, preview: bool}|null
     */
    public static function current(): ?array
    {
        $app = app();
        $today = Carbon::today()->toDateString();

        // Memoised per request (render hooks + colours all ask); keyed by date so a changed clock is respected.
        if ($app->bound(self::MEMO) && ($memo = $app->make(self::MEMO))['date'] === $today) {
            return $memo['value'];
        }

        $current = self::resolve();
        $app->instance(self::MEMO, ['date' => $today, 'value' => $current]);

        return $current;
    }

    public static function currentKey(): ?string
    {
        return self::current()['key'] ?? null;
    }

    private static function resolve(): ?array
    {
        if (! config('festive.enabled', true)) {
            return null;
        }

        try {
            $rows = FestiveTheme::query()->get()->keyBy('key');
        } catch (Throwable) {
            // Table not migrated yet: fall back to the Diwali dates in config/festive.php.
            return DiwaliTheme::configWindowActive()
                ? ['key' => 'diwali', 'def' => self::DEFINITIONS['diwali'], 'row' => null, 'preview' => false]
                : null;
        }

        $make = fn (string $key, bool $preview = false) => ['key' => $key, 'def' => self::DEFINITIONS[$key], 'row' => $rows->get($key), 'preview' => $preview];

        $preview = self::previewKey();
        if ($preview !== null) {
            return $make($preview, true);
        }

        $birthday = $rows->get('birthday');
        if ($birthday?->is_enabled && self::isUsersBirthday()) {
            return $make('birthday');
        }

        $live = self::liveGlobalKey($rows);

        return $live ? $make($live) : null;
    }

    /**
     * The company-wide theme live today (ignores previews and the personal Birthday theme).
     * A dated window beats an open-ended "on"; among dated ones, the most recently started wins.
     *
     * @param  \Illuminate\Support\Collection<string, FestiveTheme>|null  $rows
     */
    public static function liveGlobalKey($rows = null): ?string
    {
        try {
            $rows ??= FestiveTheme::query()->get();
        } catch (Throwable) {
            return null;
        }

        $today = Carbon::today();

        return $rows
            ->filter(fn (FestiveTheme $row) => isset(self::DEFINITIONS[$row->key])
                && ! (self::DEFINITIONS[$row->key]['personal'] ?? false)
                && $row->isLiveOn($today))
            ->sortByDesc(fn (FestiveTheme $row) => $row->starts_on?->timestamp ?? -1)
            ->first()?->key;
    }

    private static function previewKey(): ?string
    {
        try {
            $key = session(self::SESSION_PREVIEW);
        } catch (Throwable) {
            return null;
        }

        return is_string($key) && isset(self::DEFINITIONS[$key]) ? $key : null;
    }

    private static function isUsersBirthday(): bool
    {
        try {
            $dob = auth()->user()?->employee?->dob;
        } catch (Throwable) {
            return false;
        }

        return $dob !== null && $dob->format('m-d') === Carbon::today()->format('m-d');
    }

    // ---- Rendering helpers --------------------------------------------------------------------

    /** Panel colours for the active theme (Filament Color palettes). */
    public static function colors(): array
    {
        $current = self::current();

        if (! $current) {
            return ['primary' => Color::hex('#0F7A78'), 'warning' => Color::hex('#F5A623')];
        }

        $def = $current['def'];
        $colors = array_map(fn (string $hex) => Color::hex($hex), $def['colors']);

        $gray = match ($def['gray'] ?? null) {
            'stone' => Color::Stone,
            'slate' => Color::Slate,
            'zinc' => Color::Zinc,
            'neutral' => Color::Neutral,
            default => null,
        };

        return $gray ? $colors + ['gray' => $gray] : $colors;
    }

    /** Renders a partial for a Filament render hook, or nothing when no theme is active. */
    public static function render(string $partial): string
    {
        $current = self::current();

        if (! $current) {
            return '';
        }

        if ($current['key'] === 'diwali') {
            return view("filament.festive.diwali.{$partial}")->render();
        }

        return view("filament.festive.generic.{$partial}", ['theme' => self::viewData($current)])->render();
    }

    /** Everything the generic templates need, with placeholders filled in. */
    public static function viewData(array $current): array
    {
        $def = $current['def'];
        /** @var FestiveTheme|null $row */
        $row = $current['row'];

        $user = auth()->user();
        $company = $user?->employee?->company?->name ?? config('app.name');
        $days = $row?->dayList() ?? [];
        $mainDate = $days !== [] ? Carbon::parse(end($days)['date']) : null;
        $year = (string) ($mainDate?->year ?? now()->year);

        $replace = [
            '{name}' => $user ? Str::of(filament()->getUserName($user))->trim()->before(' ')->toString() : 'there',
            '{company}' => $company,
            '{year}' => $current['key'] === 'new_year' && $mainDate ? (string) $mainDate->year : $year,
            '{venue}' => (string) ($row?->venue ?? ''),
            '{venue_text}' => filled($row?->venue) ? ' at '.$row->venue : '',
        ];

        $fill = fn (?string $text) => trim(strtr((string) $text, $replace));

        // Before sign-in there is no name to greet; drop the ", {name}" part gracefully.
        $title = $fill($row?->title ?: $def['title']);
        if (! $user) {
            $title = trim(preg_replace('/,\s*there\b/u', '', $title));
        }

        return [
            'key' => $current['key'],
            'name' => $def['name'],
            'effect' => $def['effect'],
            'motif' => $def['motif'],
            'stage' => $def['stage'],
            'banner' => $def['banner'],
            'accents' => $def['accents'],
            'primary' => $def['colors']['primary'],
            'local' => $def['local'] ?? null,
            'title' => $title,
            'message' => $fill($row?->message ?: $def['message']),
            'days' => array_map(fn ($d) => ['name' => $d['name'], 'date' => Carbon::parse($d['date'])], $days),
            'countdown' => self::countdown($days),
            'venue' => $row?->venue,
            'preview' => $current['preview'],
        ];
    }

    /** "5 days to Annual Day" / "Today is New Year's Day" / null. */
    public static function countdown(array $days): ?string
    {
        if ($days === []) {
            return null;
        }

        $today = Carbon::today();

        foreach ($days as $day) {
            if (Carbon::parse($day['date'])->isSameDay($today)) {
                return "Today is {$day['name']}";
            }
        }

        foreach ($days as $day) {
            $date = Carbon::parse($day['date'])->startOfDay();

            if ($date->gt($today)) {
                $n = (int) $today->diffInDays($date);

                return $n === 1 ? "{$day['name']} is tomorrow" : "{$n} days to {$day['name']}";
            }
        }

        return null;
    }
}
