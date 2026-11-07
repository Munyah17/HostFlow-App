<?php

declare(strict_types=1);

// CLI cron entry point — scheduled billing automation.
// Schedule every 5 minutes (cPanel → Cron Jobs):
//   */5 * * * * php -q /home/USER/public_html/cron/run.php
// The web fallback is /cron/run?token=... (requires CRON_WEB_TOKEN in .env).

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

if (!hostflow_installed()) {
    fwrite(STDERR, "HostFlow is not installed.\n");
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    // Belt-and-braces: this script is for CLI cron only.
    http_response_code(403);
    exit('CLI only.');
}

@set_time_limit(0);

$result = App\Services\AutomationService::run();
if ($result === null) {
    echo "Another automation run is already in progress.\n";
    exit(0);
}
echo "Run #{$result['run_id']}: {$result['status']}\n";
exit($result['status'] === 'failed' ? 1 : 0);
