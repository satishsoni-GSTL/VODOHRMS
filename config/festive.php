<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Diwali theme
    |--------------------------------------------------------------------------
    |
    | The portal switches to the Diwali look between these dates (inclusive, app
    | timezone) and back to the regular GlobalSpace colours by itself afterwards.
    | Set DIWALI_THEME=false to turn it off early without a code change.
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
