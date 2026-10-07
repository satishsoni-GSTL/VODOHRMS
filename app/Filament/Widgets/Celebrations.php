<?php

namespace App\Filament\Widgets;

use App\Services\CelebrationService;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Company-wide birthdays and work anniversaries — today and the coming week — so everyone
 * sees celebrations across teams, not just their own.
 */
class Celebrations extends Widget
{
    protected static ?int $sort = 2;

    protected static string $view = 'filament.widgets.celebrations';

    protected int|string|array $columnSpan = 'full';

    public const DAYS_AHEAD = 7;

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function items(): Collection
    {
        return app(CelebrationService::class)->upcoming(auth()->user()->employee, self::DAYS_AHEAD);
    }
}
