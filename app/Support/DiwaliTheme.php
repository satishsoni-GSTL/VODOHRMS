<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class DiwaliTheme
{
    public static function isActive(): bool
    {
        $config = config('festive.diwali');

        return $config['enabled']
            && now()->between(
                Carbon::parse($config['from'])->startOfDay(),
                Carbon::parse($config['until'])->endOfDay(),
            );
    }

    /** @return array<string, Carbon> festival day name => date */
    public static function days(): array
    {
        return array_map(fn (string $date) => Carbon::parse($date), config('festive.diwali.days'));
    }

    public static function countdown(): string
    {
        $today = now()->startOfDay();
        $days = self::days();

        $current = array_search(true, array_map(fn (Carbon $date) => $date->isSameDay($today), $days), true);

        if ($current !== false) {
            return "Today is {$current}";
        }

        $upcoming = array_filter($days, fn (Carbon $date, string $name) => $date->gt($today)
            && ($today->gte($days['Diwali']) || $name === 'Diwali'), ARRAY_FILTER_USE_BOTH);

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
