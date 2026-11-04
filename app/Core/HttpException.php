<?php

declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message ?: match ($status) {
            403 => 'You do not have permission to access this page.',
            404 => 'The page you requested could not be found.',
            419 => 'Your session expired. Please go back, refresh the page and try again.',
            405 => 'Method not allowed.',
            default => 'An error occurred.',
        }, $status);
    }
}
