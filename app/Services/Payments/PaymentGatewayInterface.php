<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Online payment gateway contract (Paynow in core; others via plugins).
 * Gateways initiate and verify transactions; PaymentService applies results.
 */
interface PaymentGatewayInterface
{
    public function id(): string;

    public function name(): string;

    /** Configured and enabled. */
    public function isAvailable(): bool;

    /**
     * Start a payment. $payment is the pending payments row.
     * Return ['redirect' => url] for hosted checkout or ['instructions' => text] for push payments.
     * Must store poll URL / gateway reference via the returned 'poll_url' / 'gateway_reference' keys.
     * Throw an exception on failure.
     */
    public function initiate(array $payment, array $invoice, array $customer, array $options = []): array;

    /** Query the gateway server-side for the real status of a payment. */
    public function verify(array $payment): GatewayStatus;

    /**
     * Handle an incoming webhook/callback request body. Return the payment reference it relates to
     * (HostFlow will then call verify() — webhooks alone are never trusted) or null if invalid.
     */
    public function parseWebhook(array $post): ?string;
}
