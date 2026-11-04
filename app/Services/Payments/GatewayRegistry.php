<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class GatewayRegistry
{
    /** @var PaymentGatewayInterface[] */
    private static array $gateways = [];

    /** @return PaymentGatewayInterface[] */
    public static function all(): array
    {
        if (!isset(self::$gateways['paynow'])) {
            self::$gateways = ['paynow' => new PaynowGateway()] + self::$gateways;
        }
        return self::$gateways;
    }

    /** @return PaymentGatewayInterface[] */
    public static function available(): array
    {
        return array_filter(self::all(), fn (PaymentGatewayInterface $g) => $g->isAvailable());
    }

    public static function get(string $id): ?PaymentGatewayInterface
    {
        return self::all()[$id] ?? null;
    }

    public static function register(PaymentGatewayInterface $gateway): void
    {
        if (in_array($gateway->id(), ['paynow', 'manual'], true) || !preg_match('/^[a-z0-9_]{2,40}$/', $gateway->id())) {
            throw new \InvalidArgumentException('Invalid or reserved gateway id: ' . $gateway->id());
        }
        self::$gateways[$gateway->id()] = $gateway;
    }

    /** Test hook. */
    public static function set(string $id, PaymentGatewayInterface $gateway): void
    {
        self::all();
        self::$gateways[$id] = $gateway;
    }

    public static function reset(): void
    {
        self::$gateways = [];
    }
}
