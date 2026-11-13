<?php

namespace App\Support;

use App\Models\Company;
use Throwable;

/**
 * Pharma / field-force features (divisions, geography, positions, field organogram) stay
 * hidden until at least one company has "Field Force" switched on, so corporate-only
 * installs see no change.
 */
class FieldForce
{
    public const WORKFORCE_TYPES = [
        'ho' => 'Head Office',
        'field' => 'Field Force',
    ];

    private const CACHE_KEY = 'hrms.field-force.enabled';

    /** Memoised in the container, so it is per-request (and per-test). */
    public static function enabled(): bool
    {
        $app = app();

        if ($app->bound(self::CACHE_KEY)) {
            return $app->make(self::CACHE_KEY);
        }

        try {
            $enabled = Company::query()->where('has_field_force', true)->exists();
        } catch (Throwable) {
            // Column not migrated yet - behave like a corporate install.
            $enabled = false;
        }

        $app->instance(self::CACHE_KEY, $enabled);

        return $enabled;
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::CACHE_KEY);
    }
}
