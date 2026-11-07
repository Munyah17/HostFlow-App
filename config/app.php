<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool) Env::get('APP_DEBUG', false),
    'url' => Env::get('APP_URL', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'Africa/Harare'),
    'db' => [
        'host' => Env::get('DB_HOST', 'localhost'),
        'port' => (int) Env::get('DB_PORT', 3306),
        'database' => Env::get('DB_DATABASE', ''),
        'username' => Env::get('DB_USERNAME', ''),
        'password' => (string) Env::get('DB_PASSWORD', ''),
    ],
    'cron_web_token' => (string) Env::get('CRON_WEB_TOKEN', ''),
];
