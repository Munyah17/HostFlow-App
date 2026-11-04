<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }
        $path = Request::basePath() ?: '/';
        session_name('hostflow_sid');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '28800');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $path,
            'secure' => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $dir = BASE_PATH . '/storage/sessions';
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_start();

        $idle = 60 * 60 * 4;
        if (isset($_SESSION['_last']) && time() - $_SESSION['_last'] > $idle) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_flash']['warning'] = 'Your session timed out due to inactivity. Please sign in again.';
        }
        $_SESSION['_last'] = time();

        // Age flash data: messages set in the previous request are shown now, then removed.
        $_SESSION['_flash_now'] = $_SESSION['_flash'] ?? [];
        $_SESSION['_old_now'] = $_SESSION['_old'] ?? [];
        $_SESSION['_errors_now'] = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_flash'], $_SESSION['_old'], $_SESSION['_errors']);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    public static function flashes(): array
    {
        return $_SESSION['_flash_now'] ?? [];
    }

    public static function withInput(array $input, array $errors = []): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_csrf'], $input['current_password']);
        $_SESSION['_old'] = $input;
        $_SESSION['_errors'] = $errors;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old_now'][$key] ?? $default;
    }

    public static function errors(): array
    {
        return $_SESSION['_errors_now'] ?? [];
    }
}
