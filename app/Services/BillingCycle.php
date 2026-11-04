<?php

declare(strict_types=1);

namespace App\Services;

final class BillingCycle
{
    public const MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannual' => 6,
        'annual' => 12,
    ];

    public const LABELS = [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'semiannual' => 'Semiannual',
        'annual' => 'Annual',
    ];

    public static function isValid(?string $cycle): bool
    {
        return isset(self::MONTHS[(string) $cycle]);
    }

    /**
     * Add one billing period to a date. End-of-month dates are clamped
     * (e.g. 31 Jan + 1 month = 28/29 Feb) instead of overflowing.
     */
    public static function addPeriod(string $date, string $cycle, int $periods = 1): string
    {
        $months = self::MONTHS[$cycle] * $periods;
        $d = new \DateTimeImmutable($date);
        $day = (int) $d->format('d');
        $first = $d->modify('first day of this month')->modify(sprintf('%+d months', $months));
        $lastDay = (int) $first->format('t');
        return $first->setDate((int) $first->format('Y'), (int) $first->format('m'), min($day, $lastDay))->format('Y-m-d');
    }

    /** Price of a plan for a cycle, or null if not offered. */
    public static function planPrice(array $plan, string $cycle): ?float
    {
        $value = $plan['price_' . $cycle] ?? null;
        return $value === null || $value === '' ? null : (float) $value;
    }

    /** Cycles offered by a plan with their prices. */
    public static function planCycles(array $plan): array
    {
        $out = [];
        foreach (array_keys(self::MONTHS) as $cycle) {
            $price = self::planPrice($plan, $cycle);
            if ($price !== null) {
                $out[$cycle] = $price;
            }
        }
        return $out;
    }

    /** Equivalent monthly amount (for MRR). */
    public static function monthlyEquivalent(float $price, string $cycle): float
    {
        return $price / (self::MONTHS[$cycle] ?? 1);
    }
}
