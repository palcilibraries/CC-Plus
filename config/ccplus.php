<?php

return [
    'root_url' => env('APP_URL', 'http://localhost/'),
    'reports_path' => env('CCP_REPORTS', '/usr/local/stats_reports/'),
    'cookie_life' => strtotime(env('CCP_COOKIE_DAYS', '90') . 'days'),
    'silence_days' => env('CCP_SILENCE_DAYS', '10'),
    'max_harvest_retries' => env('CCP_MAX_HARVEST_RETRIES', '10'),
    'debug_SQL_queries' => env('CCP_DEBUG_SQL_QUERIES',0),
];
