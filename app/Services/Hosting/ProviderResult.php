<?php

declare(strict_types=1);

namespace App\Services\Hosting;

/**
 * Result of a hosting provider operation. Success is only reported when the
 * provider's API response explicitly confirms it (never from HTTP 200 alone).
 */
final class ProviderResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly mixed $data = null,
        public readonly bool $notFound = false,
        public readonly bool $permissionDenied = false,
        public readonly bool $transient = false,
    ) {
    }

    public static function ok(string $message = 'OK', mixed $data = null): self
    {
        return new self(true, $message, $data);
    }

    public static function fail(string $message, mixed $data = null, bool $notFound = false, bool $permissionDenied = false, bool $transient = false): self
    {
        return new self(false, $message, $data, $notFound, $permissionDenied, $transient);
    }
}
