<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!is_file(BASE_PATH . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('HostFlow dependencies are missing. Upload the complete release package (including the vendor/ directory) or run "composer install --no-dev".');
}
require BASE_PATH . '/vendor/autoload.php';

Env::load(BASE_PATH . '/.env');
date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Africa/Harare'));
mb_internal_encoding('UTF-8');

$debug = (bool) Env::get('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');

function hostflow_installed(): bool
{
    return is_file(BASE_PATH . '/storage/installed.lock') && is_file(BASE_PATH . '/.env');
}

if (hostflow_installed()) {
    Database::connect(config('db'));
}
