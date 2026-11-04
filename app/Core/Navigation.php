<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sidebar navigation. Core items are defined here; plugins add items through
 * Navigation::register() (only while enabled). Plugins never edit sidebar HTML.
 */
final class Navigation
{
    private static array $pluginItems = [];

    private const S = Auth::SUPER_ADMIN;
    private const A = Auth::ADMIN;
    private const C = Auth::CLIENT;

    private static function core(): array
    {
        return [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'icon' => '◈', 'roles' => [self::S, self::A], 'order' => 10],
            ['label' => 'Customers', 'url' => '/customers', 'icon' => '◉', 'roles' => [self::S, self::A], 'order' => 20],
            ['label' => 'Web Hosting', 'url' => '/hosting', 'icon' => '▣', 'roles' => [self::S, self::A], 'order' => 30, 'exclude' => ['/hosting/plans']],
            ['label' => 'Email Hosting', 'url' => '/email-hosting', 'icon' => '✉', 'roles' => [self::S, self::A], 'order' => 40],
            ['label' => 'Plans & Pricing', 'url' => '/hosting/plans', 'icon' => '▤', 'roles' => [self::S, self::A], 'order' => 50],
            ['label' => 'Invoices', 'url' => '/billing/invoices', 'icon' => '▦', 'roles' => [self::S, self::A], 'order' => 60],
            ['label' => 'Payments', 'url' => '/payments', 'icon' => '$', 'roles' => [self::S, self::A], 'order' => 70],
            ['label' => 'Automation', 'url' => '/automation', 'icon' => '↻', 'roles' => [self::S, self::A], 'order' => 80],
            ['label' => 'Support', 'url' => '/support', 'icon' => '?', 'roles' => [self::S, self::A], 'order' => 90],
            ['label' => 'Reports', 'url' => '/reports', 'icon' => '▥', 'roles' => [self::S, self::A], 'order' => 100],
            ['label' => 'Integrations', 'url' => '/integrations', 'icon' => '⇄', 'roles' => [self::S], 'order' => 110],
            ['label' => 'Administrators', 'url' => '/staff', 'icon' => '☺', 'roles' => [self::S], 'order' => 120],
            ['label' => 'Activity Log', 'url' => '/activity-log', 'icon' => '≡', 'roles' => [self::S], 'order' => 130],
            ['label' => 'Settings', 'url' => '/settings', 'icon' => '⚙', 'roles' => [self::S], 'order' => 140],

            ['label' => 'Dashboard', 'url' => '/client', 'icon' => '◈', 'roles' => [self::C], 'order' => 10, 'exact' => true],
            ['label' => 'My Services', 'url' => '/client/services', 'icon' => '▣', 'roles' => [self::C], 'order' => 20],
            ['label' => 'Email Hosting', 'url' => '/client/email', 'icon' => '✉', 'roles' => [self::C], 'order' => 30],
            ['label' => 'Order Hosting', 'url' => '/client/order', 'icon' => '+', 'roles' => [self::C], 'order' => 35],
            ['label' => 'My Invoices', 'url' => '/client/invoices', 'icon' => '▦', 'roles' => [self::C], 'order' => 40],
            ['label' => 'Payments', 'url' => '/client/payments', 'icon' => '$', 'roles' => [self::C], 'order' => 50],
            ['label' => 'Support', 'url' => '/client/support', 'icon' => '?', 'roles' => [self::C], 'order' => 60],
            ['label' => 'My Profile', 'url' => '/client/profile', 'icon' => '☺', 'roles' => [self::C], 'order' => 70],
        ];
    }

    /**
     * Register a plugin navigation item.
     * $item = ['label' => 'Domains', 'url' => '/admin/plugins/x', 'roles' => ['super_admin'], 'icon' => '◇', 'order' => 55]
     */
    public static function register(string $pluginId, array $item): void
    {
        if (empty($item['label']) || empty($item['url']) || empty($item['roles'])) {
            throw new \InvalidArgumentException("Plugin {$pluginId}: navigation items require label, url and roles.");
        }
        $roles = array_values(array_intersect((array) $item['roles'], array_keys(Auth::ROLE_LABELS)));
        self::$pluginItems[] = [
            'label' => (string) $item['label'],
            'url' => '/' . ltrim((string) $item['url'], '/'),
            'icon' => (string) ($item['icon'] ?? '◇'),
            'roles' => $roles,
            'order' => (int) ($item['order'] ?? 135),
            'plugin' => $pluginId,
        ];
    }

    public static function reset(): void
    {
        self::$pluginItems = [];
    }

    public static function snapshot(): array
    {
        return self::$pluginItems;
    }

    public static function restore(array $items): void
    {
        self::$pluginItems = $items;
    }

    public static function forRole(?string $role): array
    {
        $items = array_filter(array_merge(self::core(), self::$pluginItems), fn ($i) => in_array($role, $i['roles'], true));
        usort($items, fn ($a, $b) => $a['order'] <=> $b['order']);
        return $items;
    }

    public static function isActive(array $item): bool
    {
        $path = Request::path();
        foreach ($item['exclude'] ?? [] as $ex) {
            if ($path === $ex || str_starts_with($path, $ex . '/')) {
                return false;
            }
        }
        if (!empty($item['exact'])) {
            return $path === $item['url'];
        }
        return is_active_path($item['url']);
    }
}
