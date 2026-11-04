<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader (KEY=VALUE lines, # comments, optional quotes).
 */
final class Env
{
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
                $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }

    /** Write a .env file from an associative array (used by the installer). */
    public static function write(string $file, array $values): void
    {
        $out = "# HostFlow configuration - generated " . date('Y-m-d H:i:s') . "\n# Never commit this file. Deployments do not overwrite it.\n\n";
        foreach ($values as $key => $value) {
            $value = (string) $value;
            if ($value !== '' && preg_match('/[\s#"\'\\\\=]/', $value)) {
                $value = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
            }
            $out .= "{$key}={$value}\n";
        }
        if (file_put_contents($file, $out, LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write configuration file: {$file}");
        }
        @chmod($file, 0600);
    }
}
