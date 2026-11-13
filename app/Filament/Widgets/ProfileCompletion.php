<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MyProfile;
use App\Services\EmployeeProfileService;
use Filament\Widgets\Widget;

/**
 * Employee's own profile completion on the dashboard: overall %, each section, and exactly
 * what is missing, with a link straight to My Profile to fill it in.
 */
class ProfileCompletion extends Widget
{
    protected static string $view = 'filament.widgets.profile-completion';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    /** Cheap to compute and the first thing employees should see — render with the page. */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->employee_id !== null;
    }

    protected function getViewData(): array
    {
        return [
            'completion' => app(EmployeeProfileService::class)->completion(auth()->user()->employee),
            'url' => MyProfile::getUrl(),
        ];
    }
}
