<?php

return [
    'root_url' => env('APP_URL', 'http://localhost/'),
    'reports_path' => env('CCP_REPORTS', '/usr/local/stats_reports/'),
    'cookie_life' => strtotime(env('CCP_COOKIE_DAYS', '90') . 'days'),
    'silence_days' => env('CCP_SILENCE_DAYS', '10'),
    'max_ingest_retries' => env('CCP_MAX_INGEST_RETRIES', '10'),
    'sushi_retry_sleep' => env('CCP_SUSHI_RETRY_SLEEP', '30'),
    'sushi_retry_limit' => env('CCP_SUSHI_RETRY_LIMIT', '20')
];
