<?php

return [
    'root_url' => env('APP_URL', 'http://localhost/'),
    'reports_path' => env('CCP_REPORTS', '/usr/local/stats_reports/'),
    'cookie_life' => strtotime(env('CCP_COOKIE_DAYS', '90') . 'days'),
    'silence_days' => env('CCP_SILENCE_DAYS', '10'),
    'max_name_length' => env('CCP_MAX_NAME_LENGTH', '191'),
    'max_harvest_retries' => env('CCP_MAX_HARVEST_RETRIES', '10'),
    'debug_SQL_queries' => env('CCP_DEBUG_SQL_QUERIES',0),
    'server_admin' => env('CCP_ADMIN',null),
    'server_admin_pass' => env('CCP_ADMIN_PASSWORD',null),
    'log_login_fails' => env('CCP_LOG_LOGINFAILS',0),
];
