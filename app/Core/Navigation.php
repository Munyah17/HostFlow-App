<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sidebar navigation. Staff items are grouped into sections; clients get a flat list.
 * 'icon' is a key into the icon() helper (inline SVG).
 */
final class Navigation
{
    private const S = Auth::SUPER_ADMIN;
    private const A = Auth::ADMIN;
    private const C = Auth::CLIENT;

    private static function core(): array
    {
        return [
            // Staff — Manage
            ['label' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'dashboard', 'section' => 'Manage', 'roles' => [self::S, self::A], 'order' => 10],
            ['label' => 'Customers', 'url' => '/customers', 'icon' => 'customers', 'section' => 'Manage', 'roles' => [self::S, self::A], 'order' => 20],
            ['label' => 'Support', 'url' => '/support', 'icon' => 'support', 'section' => 'Manage', 'roles' => [self::S, self::A], 'order' => 90],
            // Staff — Services
            ['label' => 'Web Hosting', 'url' => '/hosting', 'icon' => 'hosting', 'section' => 'Services', 'roles' => [self::S, self::A], 'order' => 30, 'exclude' => ['/hosting/plans']],
            ['label' => 'Email Hosting', 'url' => '/email-hosting', 'icon' => 'email', 'section' => 'Services', 'roles' => [self::S, self::A], 'order' => 40],
            ['label' => 'Domains', 'url' => '/domains', 'icon' => 'domains', 'section' => 'Services', 'roles' => [self::S, self::A], 'order' => 45],
            ['label' => 'Plans & Pricing', 'url' => '/hosting/plans', 'icon' => 'plans', 'section' => 'Services', 'roles' => [self::S, self::A], 'order' => 50],
            // Staff — Billing
            ['label' => 'Invoices', 'url' => '/billing/invoices', 'icon' => 'invoices', 'section' => 'Billing', 'roles' => [self::S, self::A], 'order' => 60],
            ['label' => 'Payments', 'url' => '/payments', 'icon' => 'payments', 'section' => 'Billing', 'roles' => [self::S, self::A], 'order' => 70],
            ['label' => 'Expenses', 'url' => '/expenses', 'icon' => 'expenses', 'section' => 'Billing', 'roles' => [self::S, self::A], 'order' => 95],
            ['label' => 'Reports', 'url' => '/reports', 'icon' => 'reports', 'section' => 'Billing', 'roles' => [self::S, self::A], 'order' => 100],
            // Staff — System
            ['label' => 'Automation', 'url' => '/automation', 'icon' => 'automation', 'section' => 'System', 'roles' => [self::S, self::A], 'order' => 80],
            ['label' => 'Integrations', 'url' => '/integrations', 'icon' => 'integrations', 'section' => 'System', 'roles' => [self::S], 'order' => 110],
            ['label' => 'Administrators', 'url' => '/staff', 'icon' => 'staff', 'section' => 'System', 'roles' => [self::S], 'order' => 120],
            ['label' => 'Activity Log', 'url' => '/activity-log', 'icon' => 'audit', 'section' => 'System', 'roles' => [self::S], 'order' => 130],
            ['label' => 'Settings', 'url' => '/settings', 'icon' => 'settings', 'section' => 'System', 'roles' => [self::S], 'order' => 140],

            // Client portal (flat)
            ['label' => 'Dashboard', 'url' => '/client', 'icon' => 'dashboard', 'roles' => [self::C], 'order' => 10, 'exact' => true],
            ['label' => 'My Services', 'url' => '/client/services', 'icon' => 'hosting', 'roles' => [self::C], 'order' => 20],
            ['label' => 'Email Hosting', 'url' => '/client/email', 'icon' => 'email', 'roles' => [self::C], 'order' => 30],
            ['label' => 'My Domains', 'url' => '/client/domains', 'icon' => 'domains', 'roles' => [self::C], 'order' => 32],
            ['label' => 'Order Hosting', 'url' => '/client/order', 'icon' => 'order', 'roles' => [self::C], 'order' => 35],
            ['label' => 'My Invoices', 'url' => '/client/invoices', 'icon' => 'invoices', 'roles' => [self::C], 'order' => 40],
            ['label' => 'Payments', 'url' => '/client/payments', 'icon' => 'payments', 'roles' => [self::C], 'order' => 50],
            ['label' => 'Support', 'url' => '/client/support', 'icon' => 'support', 'roles' => [self::C], 'order' => 60],
            ['label' => 'My Profile', 'url' => '/client/profile', 'icon' => 'profile', 'roles' => [self::C], 'order' => 70],
        ];
    }

    /** Items visible to a role, ordered by section (ranked by its first item) then order. */
    public static function forRole(?string $role): array
    {
        $items = array_values(array_filter(self::core(), fn ($i) => in_array($role, $i['roles'], true)));
        $sectionRank = [];
        foreach ($items as $i) {
            $s = (string) ($i['section'] ?? '');
            $sectionRank[$s] = min($sectionRank[$s] ?? PHP_INT_MAX, $i['order']);
        }
        usort($items, fn ($a, $b) => ($sectionRank[(string) ($a['section'] ?? '')] <=> $sectionRank[(string) ($b['section'] ?? '')]) ?: ($a['order'] <=> $b['order']));
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
