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
            self::$gateways = ['paynow' => new PaynowGateway(), 'stripe' => new StripeGateway()] + self::$gateways;
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
