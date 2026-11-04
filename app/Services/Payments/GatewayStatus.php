<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** A verified status reported by a payment gateway. */
final class GatewayStatus
{
    public const PAID = 'paid';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public function __construct(
        public readonly string $state,
        public readonly string $gatewayStatus,
        public readonly ?float $amount = null,
        public readonly ?string $gatewayReference = null,
        public readonly string $raw = '',
    ) {
    }
}
