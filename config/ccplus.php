<?php

return [
    'constants' => [
        'CCPLUSROOTURL' => env('APP_URL', 'http://localhost/'),
        'CCPLUSREPORTS' => env('CCP_REPORTS', '/usr/local/stats_reports/'),
        'CCP_COOKIE_LIFE' => strtotime(env('CCP_COOKIE_DAYS', '90') . 'days'),
        'SILENCE_DAYS' => env('CCP_SILENCE_DAYS', '10'),
        'MAX_INGEST_RETRIES' => env('CCP_MAX_INGEST_RETRIES', '10'),
        'SUSHI_RETRY_SLEEP' => env('CCP_SUSHI_RETRY_SLEEP', '30'),
        'SUSHI_RETRY_LIMIT' => env('CCP_SUSHI_RETRY_LIMIT', '20')
    ]
];
