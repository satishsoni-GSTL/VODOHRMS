<?php

namespace App\Filament\Concerns;

use App\Support\FieldForce;

/**
 * For Filament resources that only make sense for pharma / field-force companies: hidden
 * (and inaccessible) until a company has "Field Force" switched on.
 */
trait GatedByFieldForce
{
    public static function canViewAny(): bool
    {
        return FieldForce::enabled() && parent::canViewAny();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Field Force';
    }

    /** Extra header actions on the list page (overridden per resource). */
    public static function listHeaderActions(): array
    {
        return [];
    }
}
