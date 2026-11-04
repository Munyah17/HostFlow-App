<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?string $basePath = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** Base URL path the application is mounted at (e.g. "" or "/billing"). */
    public static function basePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }
        $appUrl = (string) Env::get('APP_URL', '');
        if ($appUrl !== '') {
            return self::$basePath = rtrim((string) (parse_url($appUrl, PHP_URL_PATH) ?? ''), '/');
        }
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($dir !== '' && !str_starts_with($uri, $dir) && str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -7);
        }
        return self::$basePath = $dir;
    }

    public static function setBasePath(?string $path): void
    {
        self::$basePath = $path;
    }

    public static function path(): string
    {
        $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public static function post(): array
    {
        return $_POST;
    }

    /** Return selected keys from POST, trimmed. */
    public static function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $_POST[$key] ?? null;
            $out[$key] = is_string($value) ? trim($value) : $value;
        }
        return $out;
    }

    public static function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $file : null;
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
