<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Diwali's hand-made artwork (filament/festive/diwali + public/css|js/festive/diwali.*).
 * Whether it shows is decided by FestiveThemes (Organization → Celebration Themes); the
 * dates in config/festive.php are only the fallback until the festive_themes table exists.
 */
class DiwaliTheme
{
    public static function isActive(): bool
    {
        return FestiveThemes::currentKey() === 'diwali';
    }

    /** The original config-driven window (used before the festive_themes table is migrated). */
    public static function configWindowActive(): bool
    {
        // Defaults to off so a stale config cache on deploy can't break the portal.
        return config('festive.diwali.enabled', false)
            && now()->between(
                Carbon::parse(config('festive.diwali.from'))->startOfDay(),
                Carbon::parse(config('festive.diwali.until'))->endOfDay(),
            );
    }

    /** @return array<string, Carbon> festival day name => date */
    public static function days(): array
    {
        $row = FestiveThemes::current()['row'] ?? null;

        if ($row && $row->key === 'diwali' && $row->dayList() !== []) {
            return collect($row->dayList())->mapWithKeys(fn ($d) => [$d['name'] => Carbon::parse($d['date'])])->all();
        }

        return array_map(fn (string $date) => Carbon::parse($date), config('festive.diwali.days', []));
    }

    public static function mainDay(): string
    {
        $row = FestiveThemes::current()['row'] ?? null;

        return ($row && $row->key === 'diwali' && filled($row->main_day)) ? $row->main_day : 'Diwali';
    }

    public static function countdown(): string
    {
        $today = now()->startOfDay();
        $days = self::days();
        $main = self::mainDay();

        if ($days === []) {
            return 'Wishing you a happy Diwali';
        }

        $current = array_search(true, array_map(fn (Carbon $date) => $date->isSameDay($today), $days), true);

        if ($current !== false) {
            return "Today is {$current}";
        }

        $mainDate = $days[$main] ?? end($days);

        $upcoming = array_filter($days, fn (Carbon $date, string $name) => $date->gt($today)
            && ($today->gte($mainDate) || $name === $main), ARRAY_FILTER_USE_BOTH);

        if ($upcoming === []) {
            return 'Thank you for celebrating with us';
        }

        $next = array_key_first($upcoming);
        $remaining = (int) $today->diffInDays($upcoming[$next]);

        return $remaining === 1 ? "{$next} is tomorrow" : "{$remaining} days to {$next}";
    }

    /** Renders a partial for a Filament render hook, or nothing outside the festive window. */
    public static function render(string $partial): string
    {
        return self::isActive() ? view("filament.festive.diwali.{$partial}")->render() : '';
    }
}
