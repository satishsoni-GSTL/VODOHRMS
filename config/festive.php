<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Celebration themes
    |--------------------------------------------------------------------------
    |
    | Themes (Diwali, New Year, Christmas, Annual Day, Birthday, Holi, Independence Day,
    | Republic Day) are switched on and scheduled from Organization → Celebration Themes.
    | FESTIVE_THEMES=false is a master switch that turns every theme off without a deploy.
    |
    */

    'enabled' => env('FESTIVE_THEMES', true),

    /*
    |--------------------------------------------------------------------------
    | Diwali fallback
    |--------------------------------------------------------------------------
    |
    | Only used until the festive_themes table is migrated (and to seed it). After that the
    | Diwali dates are edited in the panel. DIWALI_THEME=false turns this fallback off.
    |
    */

    'diwali' => [
        'enabled' => env('DIWALI_THEME', true),
        'from' => '2026-11-01',
        'until' => '2026-11-12',

        // Shown with a countdown on the login page and dashboard. 'Diwali' is the main day.
        'days' => [
            'Dhanteras' => '2026-11-06',
            'Choti Diwali' => '2026-11-07',
            'Diwali' => '2026-11-08',
            'Govardhan' => '2026-11-10',
            'Bhai Dooj' => '2026-11-11',
        ],
    ],

];
