<?php

namespace App\Models;

use App\Support\FestiveThemes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * HR-controlled settings for one celebration theme (look and effects live in
 * App\Support\FestiveThemes::DEFINITIONS).
 */
class FestiveTheme extends Model
{
    protected $fillable = ['key', 'is_enabled', 'starts_on', 'ends_on', 'title', 'message', 'days', 'main_day', 'venue'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'days' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FestiveThemes::flush());
    }

    public function definition(): ?array
    {
        return FestiveThemes::definition($this->key);
    }

    public function isPersonal(): bool
    {
        return (bool) ($this->definition()['personal'] ?? false);
    }

    /** Switched on, and today is inside its window (no dates = on until switched off). */
    public function isLiveOn(Carbon $day): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        if ($this->starts_on && $day->lt($this->starts_on->copy()->startOfDay())) {
            return false;
        }

        return ! ($this->ends_on && $day->gt($this->ends_on->copy()->endOfDay()));
    }

    /** @return array<int, array{name: string, date: string}> sorted by date */
    public function dayList(): array
    {
        return collect($this->days ?? [])
            ->filter(fn ($d) => filled($d['name'] ?? null) && filled($d['date'] ?? null))
            ->sortBy('date')
            ->values()
            ->all();
    }

    /** Live now / Scheduled / Ended / On / Off — for the admin list. */
    public function statusLabel(): string
    {
        if (! $this->is_enabled) {
            return 'Off';
        }

        if ($this->isPersonal()) {
            return 'On (birthdays)';
        }

        $today = Carbon::today();

        if ($this->isLiveOn($today)) {
            return FestiveThemes::liveGlobalKey() === $this->key ? 'Live now' : 'Overlaps — another theme is showing';
        }

        return $this->starts_on && $today->lt($this->starts_on) ? 'Scheduled' : 'Ended';
    }
}
