<?php

$url = env('FILESHARE_URL');

return [

    /*
    | Public base URL of the file-sharing site, e.g. https://file.globalspace.in. Point that
    | domain's vhost at this same app's public/ folder. When set, the share pages (/s/...) are
    | served ONLY on that host and that host serves nothing else (see RestrictFileShareHost).
    | Leave empty in local dev to serve the share pages on APP_URL instead.
    */
    'url' => $url ? rtrim($url, '/') : null,

    'domain' => $url ? parse_url($url, PHP_URL_HOST) : null,

    // Minutes an emailed access code stays valid.
    'otp_ttl_minutes' => (int) env('FILESHARE_OTP_TTL', 10),

    // Wrong guesses allowed per code before a new one must be requested.
    'otp_max_attempts' => 5,

    // Codes a recipient may request per 10 minutes.
    'otp_max_sends' => 3,

    // Hours a verified recipient stays signed in on the share page (same browser).
    'session_hours' => (int) env('FILESHARE_SESSION_HOURS', 12),

    // Upload limit in KB (PHP's upload_max_filesize / post_max_size must allow it too).
    'max_upload_kb' => (int) env('FILESHARE_MAX_UPLOAD_KB', 51200),

    'disk' => 'local',

    'directory' => 'shared-files',
];
