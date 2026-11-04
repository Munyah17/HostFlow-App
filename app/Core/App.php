<?php

declare(strict_types=1);

namespace App\Core;

use App\Plugins\PluginManager;

final class App
{
    public static function router(): Router
    {
        $router = new Router();
        (require BASE_PATH . '/app/routes.php')($router);
        if (hostflow_installed()) {
            PluginManager::instance()->registerRoutes($router);
        }
        return $router;
    }

    public static function handleHttp(): void
    {
        self::securityHeaders();
        try {
            Session::start();
            $path = Request::path();
            $installing = $path === '/install' || str_starts_with($path, '/install/');
            if (!hostflow_installed() && !$installing && !str_starts_with($path, '/assets/')) {
                redirect('/install');
            }
            if (hostflow_installed()) {
                PluginManager::instance()->boot();
            }
            $result = self::router()->dispatch(Request::method(), $path);
            if (is_string($result)) {
                echo $result;
            }
        } catch (HttpException $e) {
            self::renderError($e->status, $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'path' => $_SERVER['REQUEST_URI'] ?? '', 'trace' => substr($e->getTraceAsString(), 0, 3000)]);
            $msg = config('debug') ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'An unexpected error occurred. The error has been logged.';
            self::renderError(500, $msg);
        }
    }

    private static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self' https://www.paynow.co.zw; frame-ancestors 'self'; base-uri 'self'");
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function renderError(int $status, string $message): void
    {
        http_response_code($status);
        if (Request::wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
            return;
        }
        $titles = [403 => 'Access denied', 404 => 'Page not found', 405 => 'Method not allowed', 419 => 'Session expired', 500 => 'Something went wrong'];
        $data = ['status' => $status, 'title' => $titles[$status] ?? 'Error', 'message' => $message];
        try {
            $layout = (hostflow_installed() && Database::isConnected() && Auth::check()) ? 'layouts.app' : 'layouts.auth';
            echo View::render('errors.error', $data, $layout);
        } catch (\Throwable) {
            echo '<h1>' . e($data['title']) . '</h1><p>' . e($message) . '</p>';
        }
    }
}
