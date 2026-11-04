<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Notifications\NotificationService;

/**
 * Domain registrations and renewals. Registrations are fulfilled manually at the
 * registrar (or by a registrar plugin): payment moves a domain to
 * pending_registration, staff confirms it and the expiry clock starts there.
 */
final class DomainService
{
    public const STATUSES = ['pending_payment', 'pending_registration', 'active', 'expired', 'transferred_away', 'cancelled'];

    /** Extract the TLD (lowercased, with the leading dot) from a domain name. */
    public static function tldOf(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $pos = strpos($domain, '.');
        return $pos === false ? '' : substr($domain, $pos);
    }

    /** Active pricing row for a TLD, or null. */
    public static function pricingFor(string $tld): ?array
    {
        return db()->fetch("SELECT * FROM domain_pricing WHERE tld = ? AND status = 'active'", [strtolower($tld)]);
    }

    /**
     * Create a domain order (domain in pending_payment + invoice with a domain item).
     * Returns ['domain_id' => int, 'invoice_id' => int].
     */
    public static function order(int $customerId, string $domain, int $years = 1, array $options = []): array
    {
        $domain = strtolower(trim($domain));
        $years = max(1, min(10, $years));
        $tld = self::tldOf($domain);
        $pricing = self::pricingFor($tld);
        $price = isset($options['price']) && $options['price'] !== '' && $options['price'] !== null
            ? round((float) $options['price'], 2)
            : ($pricing ? round((float) $pricing['register_price'] * $years, 2) : null);
        if ($price === null) {
            throw new \InvalidArgumentException('The extension ' . ($tld ?: '(none)') . ' is not sold. Check Domain pricing.');
        }
        $existing = db()->fetch("SELECT id, status FROM domains WHERE domain = ?", [$domain]);
        if ($existing && !in_array($existing['status'], ['transferred_away', 'cancelled'], true)) {
            throw new \InvalidArgumentException("{$domain} is already on the system.");
        }

        return db()->transaction(function ($db) use ($customerId, $domain, $tld, $years, $price, $pricing, $options) {
            $domainId = $existingId = (int) ($db->fetchColumn('SELECT id FROM domains WHERE domain = ? FOR UPDATE', [$domain]) ?: 0);
            if ($domainId) {
                $db->update('domains', [
                    'customer_id' => $customerId,
                    'service_id' => $options['service_id'] ?? null,
                    'status' => 'pending_payment',
                    'auto_renew' => !empty($options['auto_renew']) ? 1 : 0,
                    'registration_price' => $price,
                    'renewal_price' => $pricing ? (float) $pricing['renew_price'] * $years : $price,
                    'notes' => $options['notes'] ?? null,
                    'updated_at' => now(),
                ], 'id = :id', ['id' => $domainId]);
            } else {
                $domainId = $db->insert('domains', [
                    'customer_id' => $customerId,
                    'service_id' => $options['service_id'] ?? null,
                    'domain' => $domain,
                    'tld' => $tld,
                    'status' => 'pending_payment',
                    'auto_renew' => !empty($options['auto_renew']) ? 1 : 0,
                    'registration_price' => $price,
                    'renewal_price' => $pricing ? (float) $pricing['renew_price'] * $years : $price,
                    'notes' => $options['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $invoiceId = InvoiceService::create($customerId, [[
                'domain_id' => $domainId,
                'item_type' => 'domain',
                'description' => sprintf('Domain registration — %s (%d year%s)', $domain, $years, $years > 1 ? 's' : ''),
                'unit_price' => $price,
                'meta' => ['action' => 'register', 'years' => $years],
            ]], ['due_date' => today()]);
            return ['domain_id' => $domainId, 'invoice_id' => $invoiceId];
        });
    }

    /**
     * Add a domain that is already registered at the registrar (no invoice).
     * Staff use this to bring existing client domains under management.
     */
    public static function createManual(int $customerId, string $domain, array $data): int
    {
        $domain = strtolower(trim($domain));
        $tld = self::tldOf($domain);
        if (db()->fetchColumn('SELECT COUNT(*) FROM domains WHERE domain = ?', [$domain])) {
            throw new \InvalidArgumentException("{$domain} is already on the system.");
        }
        return db()->insert('domains', [
            'customer_id' => $customerId,
            'service_id' => $data['service_id'] ?? null,
            'domain' => $domain,
            'tld' => $tld,
            'status' => 'active',
            'registrar' => $data['registrar'] ?? null,
            'registrar_reference' => $data['registrar_reference'] ?? null,
            'nameservers' => $data['nameservers'] ?? null,
            'registered_at' => $data['registered_at'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'auto_renew' => !empty($data['auto_renew']) ? 1 : 0,
            'registration_price' => round((float) ($data['registration_price'] ?? 0), 2),
            'renewal_price' => round((float) ($data['renewal_price'] ?? 0), 2),
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Mark a paid domain as registered at the registrar. */
    public static function markRegistered(int $id, array $data): void
    {
        $d = db()->fetch('SELECT * FROM domains WHERE id = ?', [$id]);
        if (!$d) {
            throw new \InvalidArgumentException('Domain not found.');
        }
        if (!in_array($d['status'], ['pending_registration', 'pending_payment'], true)) {
            throw new \InvalidArgumentException('Only domains awaiting registration can be marked registered.');
        }
        $registeredAt = $data['registered_at'] ?: today();
        $expiry = $data['expiry_date'] ?: date('Y-m-d', strtotime($registeredAt . ' +1 year'));
        db()->update('domains', [
            'status' => 'active',
            'registered_at' => $registeredAt,
            'expiry_date' => $expiry,
            'registrar' => ($data['registrar'] ?? '') !== '' ? $data['registrar'] : $d['registrar'],
            'registrar_reference' => ($data['registrar_reference'] ?? '') !== '' ? $data['registrar_reference'] : $d['registrar_reference'],
            'nameservers' => ($data['nameservers'] ?? '') !== '' ? $data['nameservers'] : $d['nameservers'],
            'updated_at' => now(),
        ], 'id = :id', ['id' => $id]);
        NotificationService::notifyCustomer((int) $d['customer_id'], 'domain_registered', [
            'domain' => $d['domain'],
            'expiry_date' => date_fmt($expiry),
            'domain_url' => absolute_url('/client/domains/' . $id),
        ], 'domain_registered:' . $id);
    }

    /**
     * Generate the renewal invoice for a domain's current registration period.
     * Idempotent through (domain_id, item_type, period_start). Returns the invoice id
     * (new or existing) or null when not applicable.
     */
    public static function generateRenewal(int $domainId, bool $force = false): ?int
    {
        $created = false;
        $invoiceId = db()->transaction(function ($db) use ($domainId, $force, &$created) {
            $d = $db->fetch('SELECT * FROM domains WHERE id = ? FOR UPDATE', [$domainId]);
            if (!$d || !in_array($d['status'], ['active', 'expired'], true) || !$d['expiry_date']) {
                return null;
            }
            $windowEnd = date('Y-m-d', strtotime('+' . Settings::int('renewal_invoice_days') . ' days'));
            if (!$force && $d['expiry_date'] > $windowEnd) {
                return null;
            }
            $existing = $db->fetchColumn(
                "SELECT ii.invoice_id FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                 WHERE ii.domain_id = ? AND ii.item_type = 'domain' AND ii.period_start = ? AND i.status <> 'cancelled' LIMIT 1",
                [$domainId, $d['expiry_date']]
            );
            if ($existing) {
                return (int) $existing;
            }
            $start = $d['expiry_date'];
            $end = date('Y-m-d', strtotime($start . ' +1 year'));
            $price = (float) $d['renewal_price'] > 0 ? (float) $d['renewal_price'] : (float) (self::pricingFor($d['tld'])['renew_price'] ?? 0);
            if ($price <= 0) {
                AutomationLog::record('domain_renewals', 'renewal_invoice', 'skipped', "No renewal price for {$d['domain']} ({$d['tld']})", 'domain', $domainId);
                return null;
            }
            $created = true;
            return InvoiceService::create((int) $d['customer_id'], [[
                'domain_id' => $domainId,
                'item_type' => 'domain',
                'description' => sprintf('Domain renewal — %s (%s to %s)', $d['domain'], date_fmt($start), date_fmt(date('Y-m-d', strtotime($end . ' -1 day')))),
                'unit_price' => $price,
                'period_start' => $start,
                'period_end' => $end,
                'meta' => ['action' => 'renew'],
            ]], ['due_date' => max($start, today()), 'created_by' => null]);
        });
        if ($invoiceId && $created) {
            InvoiceService::notifyCreated($invoiceId);
        }
        return $invoiceId;
    }

    /**
     * Apply a paid invoice's domain items. Runs inside the payment transaction;
     * rows must be locked by the caller.
     */
    public static function applyPaidItem(\App\Core\Database $db, array $item): void
    {
        $d = $db->fetch('SELECT * FROM domains WHERE id = ? FOR UPDATE', [$item['domain_id']]);
        if (!$d) {
            return;
        }
        $meta = json_decode((string) $item['meta'], true) ?: [];
        switch ($meta['action'] ?? '') {
            case 'register':
                if (in_array($d['status'], ['pending_payment', 'cancelled'], true)) {
                    $db->update('domains', ['status' => 'pending_registration', 'updated_at' => now()], 'id = :id', ['id' => $d['id']]);
                    NotificationService::alertStaff('Domain paid — register at registrar', "{$d['domain']} (customer #{$d['customer_id']}) is paid and awaiting registration.", 'domain_paid:' . $d['id']);
                }
                break;
            case 'renew':
                if ($d['expiry_date'] === $item['period_start'] && $item['period_end']) {
                    $db->update('domains', [
                        'expiry_date' => $item['period_end'],
                        'status' => 'active',
                        'updated_at' => now(),
                    ], 'id = :id', ['id' => $d['id']]);
                    NotificationService::notifyCustomer((int) $d['customer_id'], 'domain_renewed', [
                        'domain' => $d['domain'],
                        'expiry_date' => date_fmt($item['period_end']),
                        'domain_url' => absolute_url('/client/domains/' . $d['id']),
                    ], 'domain_renewed:' . $d['id'] . ':' . $item['period_end']);
                } else {
                    AutomationLog::record('billing', 'domain_renewal_date_mismatch', 'info', "Invoice item {$item['id']} period {$item['period_start']} does not match domain expiry {$d['expiry_date']}; expiry left unchanged for staff review.", 'domain', (int) $d['id']);
                }
                break;
        }
        $db->update('invoice_items', ['applied_at' => now()], 'id = :id', ['id' => $item['id']]);
    }

    /** Cron: generate renewal invoices for domains expiring inside the billing window. */
    public static function renewalsDue(): array
    {
        $windowEnd = date('Y-m-d', strtotime('+' . Settings::int('renewal_invoice_days') . ' days'));
        return db()->fetchAll(
            "SELECT id, domain, expiry_date FROM domains
             WHERE status = 'active' AND auto_renew = 1 AND expiry_date IS NOT NULL AND expiry_date <= ?",
            [$windowEnd]
        );
    }

    /** Cron: mark active domains past expiry as expired and notify the customer once. */
    public static function expireOverdue(): int
    {
        $rows = db()->fetchAll("SELECT * FROM domains WHERE status = 'active' AND expiry_date < CURDATE()");
        foreach ($rows as $d) {
            db()->update('domains', ['status' => 'expired', 'updated_at' => now()], 'id = :id', ['id' => $d['id']]);
            NotificationService::notifyCustomer((int) $d['customer_id'], 'domain_expired', [
                'domain' => $d['domain'],
                'expiry_date' => date_fmt($d['expiry_date']),
                'domain_url' => absolute_url('/client/domains/' . $d['id']),
            ], 'domain_expired:' . $d['id'] . ':' . $d['expiry_date']);
            AutomationLog::record('expire_domains', 'expired', 'info', "{$d['domain']} expired on {$d['expiry_date']}", 'domain', (int) $d['id']);
        }
        return count($rows);
    }

    /** Active domains expiring within N days (for reminder thresholds). */
    public static function expiring(): array
    {
        return db()->fetchAll(
            "SELECT d.*, c.full_name, c.email FROM domains d JOIN customers c ON c.id = d.customer_id
             WHERE d.status = 'active' AND d.expiry_date IS NOT NULL AND d.expiry_date >= CURDATE()"
        );
    }
}
