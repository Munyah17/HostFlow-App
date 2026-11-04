<?php

declare(strict_types=1);

namespace App\Services\Hosting;

/**
 * Maps hosting provider types to implementations. Core ships "cpanel_whm";
 * plugins may register further types (e.g. "plesk", "directadmin").
 */
final class ProviderRegistry
{
    private static array $types = [];
    /** Test hook: replaces provider construction (used by the automated test suite only). */
    private static $override = null;

    public static function types(): array
    {
        return ['cpanel_whm' => ['label' => 'cPanel & WHM (API 1)', 'default_port' => 2087]] + array_map(fn ($t) => ['label' => $t['label'], 'default_port' => $t['default_port']], self::$types);
    }

    /** @param callable(array $row): HostingProviderInterface $factory */
    public static function register(string $type, string $label, callable $factory, int $defaultPort = 443): void
    {
        if ($type === 'cpanel_whm' || !preg_match('/^[a-z0-9_]{2,40}$/', $type)) {
            throw new \InvalidArgumentException("Invalid or reserved hosting provider type: {$type}");
        }
        self::$types[$type] = ['label' => $label, 'factory' => $factory, 'default_port' => $defaultPort];
    }

    public static function reset(): void
    {
        self::$types = [];
    }

    public static function setOverride(?callable $factory): void
    {
        self::$override = $factory;
    }

    public static function make(array $providerRow): HostingProviderInterface
    {
        if (self::$override) {
            return (self::$override)($providerRow);
        }
        $type = (string) $providerRow['type'];
        if ($type === 'cpanel_whm') {
            return CpanelWhmProvider::fromRow($providerRow);
        }
        if (isset(self::$types[$type])) {
            $provider = (self::$types[$type]['factory'])($providerRow);
            if (!$provider instanceof HostingProviderInterface) {
                throw new \RuntimeException("Provider type {$type} did not return a HostingProviderInterface.");
            }
            return $provider;
        }
        throw new \RuntimeException("Hosting provider type '{$type}' is not available. Is its plugin enabled?");
    }

    public static function forId(?int $providerId): ?HostingProviderInterface
    {
        if (!$providerId) {
            return null;
        }
        $row = db()->fetch('SELECT * FROM hosting_providers WHERE id = ?', [$providerId]);
        return $row ? self::make($row) : null;
    }
}
