<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Plugins\PluginManager;
use App\Services\AutomationService;
use App\Services\Payments\GatewayRegistry;
use App\Services\PaymentService;

final class PublicController extends Controller
{
    public function home(): never
    {
        redirect(Auth::check() ? Auth::home() : '/login');
    }

    /** Paynow status update (resulturl). The hash is verified, then the status is confirmed by polling. */
    public function paynowWebhook(): string
    {
        return $this->handleWebhook('paynow');
    }

    public function gatewayWebhook(string $gateway): string
    {
        if ($gateway === 'paynow') {
            return $this->handleWebhook('paynow');
        }
        return $this->handleWebhook($gateway);
    }

    private function handleWebhook(string $gatewayId): string
    {
        $gateway = GatewayRegistry::get($gatewayId);
        if (!$gateway) {
            http_response_code(404);
            return 'Unknown gateway';
        }
        $reference = $gateway->parseWebhook($_POST);
        if (!$reference) {
            http_response_code(400);
            return 'Invalid notification';
        }
        $payment = db()->fetch('SELECT * FROM payments WHERE reference = ? AND gateway = ?', [$reference, $gatewayId]);
        if (!$payment) {
            Logger::error('Webhook for unknown payment', ['gateway' => $gatewayId, 'reference' => $reference], 'payments');
            http_response_code(404);
            return 'Unknown payment';
        }
        try {
            PaymentService::verify((int) $payment['id']);
        } catch (\Throwable $e) {
            Logger::error('Webhook processing failed: ' . $e->getMessage(), ['reference' => $reference], 'payments');
            http_response_code(500);
            return 'Error';
        }
        return 'OK';
    }

    /** Optional web-triggered cron for hosts without CLI cron (requires CRON_WEB_TOKEN). */
    public function webCron(): string
    {
        $token = (string) config('cron_web_token');
        $given = (string) ($_GET['token'] ?? '');
        if ($token === '' || strlen($token) < 24 || !hash_equals($token, $given)) {
            abort(404);
        }
        @set_time_limit(300);
        PluginManager::instance()->boot();
        $result = AutomationService::run();
        header('Content-Type: text/plain');
        return $result === null ? "Another automation run is in progress.\n" : 'Run #' . $result['run_id'] . ': ' . $result['status'] . "\n";
    }

    /** Serve static assets from an ENABLED plugin's assets/ directory. */
    public function pluginAsset(string $plugin, string $file): never
    {
        $record = db()->fetch("SELECT * FROM plugins WHERE plugin_id = ? AND status = 'enabled'", [$plugin]);
        $info = $record ? PluginManager::instance()->find($plugin) : null;
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'woff2' => 'font/woff2', 'woff' => 'font/woff'];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!$info || !$info['path'] || !isset($types[$ext]) || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
            abort(404);
        }
        $base = realpath($info['path'] . '/assets');
        $path = $base ? realpath($base . '/' . $file) : false;
        if (!$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
            abort(404);
        }
        header('Content-Type: ' . $types[$ext]);
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}
