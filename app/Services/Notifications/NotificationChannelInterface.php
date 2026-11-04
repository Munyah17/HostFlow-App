<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * A delivery channel (email in core; SMS/WhatsApp via plugins).
 * HostFlow decides what/when/who; the channel only delivers.
 */
interface NotificationChannelInterface
{
    /** Unique channel id, e.g. "email", "whatsapp". */
    public function id(): string;

    public function label(): string;

    /** Whether the channel is configured and should receive notifications. */
    public function isEnabled(): bool;

    /** Recipient address for a customer (email, phone ...) or null to skip. */
    public function recipientFor(array $customer): ?string;

    /**
     * Deliver a notification. $notification has: recipient, subject, body (HTML), text (plain), template.
     * Throw an exception on failure (the queue retries later).
     */
    public function send(array $notification): void;
}
