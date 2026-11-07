<?php

declare(strict_types=1);

namespace App\Services\Hosting;

/**
 * Maps hosting provider types to implementations. Core ships "cpanel_whm".
 */
final class ProviderRegistry
{
    /** Test hook: replaces provider construction (used by the automated test suite only). */
    private static $override = null;

    public static function types(): array
    {
        return ['cpanel_whm' => ['label' => 'cPanel & WHM (API 1)', 'default_port' => 2087]];
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
        throw new \RuntimeException("Hosting provider type '{$type}' is not available.");
    }

    public static function forId(?int $providerId): ?HostingProviderInterface
    {
        if (!$providerId) {
            return null;
        }
        $row = db()->fetch('SELECT * FROM hosting_providers WHERE id = ?', [$providerId]);
        return $row ? self::make($row) : null;
    }

    public static function setOverride(?callable $factory): void
    {
        self::$override = $factory;
    }
}
