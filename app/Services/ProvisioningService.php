<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Services\Hosting\CpanelWhmProvider;
use App\Services\Hosting\HostingProviderInterface;
use App\Services\Hosting\ProviderRegistry;
use App\Services\Notifications\NotificationService;

/**
 * Hosting account lifecycle. Local service state only changes after the
 * provider has confirmed (and HostFlow has verified) the external operation.
 * All methods return ['success' => bool, 'message' => string].
 */
final class ProvisioningService
{
    private static function result(bool $ok, string $message): array
    {
        return ['success' => $ok, 'message' => $message];
    }

    private static function service(int $id): array
    {
        $s = db()->fetch('SELECT s.*, p.name AS plan_name, p.provider_package AS plan_package, c.email AS customer_email, c.full_name FROM services s JOIN hosting_plans p ON p.id = s.plan_id JOIN customers c ON c.id = s.customer_id WHERE s.id = ?', [$id]);
        if (!$s) {
            throw new \RuntimeException('Service not found.');
        }
        return $s;
    }

    private static function provider(array $s): HostingProviderInterface
    {
        if (!$s['provider_id']) {
            throw new \RuntimeException('No hosting provider is assigned to this service. Assign one on the plan or service first.');
        }
        $row = db()->fetch('SELECT * FROM hosting_providers WHERE id = ?', [$s['provider_id']]);
        if (!$row) {
            throw new \RuntimeException('The assigned hosting provider no longer exists.');
        }
        if ($row['status'] !== 'active') {
            throw new \RuntimeException("Hosting provider '{$row['name']}' is disabled.");
        }
        return ProviderRegistry::make($row);
    }

    private static function log(string $action, int $serviceId, bool $ok, string $message, bool $automated): void
    {
        AutomationLog::record($automated ? 'automation' : 'manual', $action, $ok ? 'success' : 'failed', $message, 'service', $serviceId);
        if (!$automated) {
            audit('service.' . $action . ($ok ? '' : '_failed'), 'service', $serviceId, $message);
        }
    }

    /** Generate a valid, unused cPanel username from the domain. */
    public static function generateUsername(string $domain, HostingProviderInterface $provider): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $base = ltrim($base, '0123456789');
        if ($base === '' || str_starts_with($base, 'test')) {
            $base = 'u' . $base;
        }
        $base = substr($base, 0, 8);
        for ($i = 0; $i < 25; $i++) {
            $candidate = $i === 0 ? $base : substr($base, 0, 6) . random_int(10, 99);
            if (!CpanelWhmProvider::validUsername($candidate)) {
                continue;
            }
            if (db()->fetchColumn('SELECT id FROM services WHERE provider_username = ?', [$candidate])) {
                continue;
            }
            $check = $provider->getAccount($candidate);
            if ($check->notFound) {
                return $candidate;
            }
            if (!$check->success) {
                throw new \RuntimeException('Could not check username availability: ' . $check->message);
            }
        }
        throw new \RuntimeException('Unable to find an available username for ' . $domain);
    }

    /**
     * Create the hosting account. Safe to retry: an account that already exists for
     * the reserved username and domain is adopted instead of being created twice.
     */
    public static function provision(int $serviceId, bool $automated = true): array
    {
        $s = self::service($serviceId);
        if (!in_array($s['status'], ['provisioning', 'provisioning_failed'], true)) {
            return self::result(false, 'Service is not awaiting provisioning (status: ' . status_label($s['status']) . ').');
        }
        // Claim: only one process provisions a service at a time.
        $claimed = db()->query("UPDATE services SET provision_attempts = provision_attempts + 1, status = 'provisioning', updated_at = NOW() WHERE id = ? AND status IN ('provisioning','provisioning_failed') AND (updated_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE) OR provision_attempts = ? )", [$serviceId, (int) $s['provision_attempts']])->rowCount();
        if (!$claimed) {
            return self::result(false, 'Provisioning is already in progress for this service.');
        }
        try {
            $provider = self::provider($s);
            $package = $s['plan_package'] ?: $s['provider_package'];
            if (!$package) {
                throw new \RuntimeException('The hosting plan has no provider package mapped.');
            }
            $username = $s['provider_username'];
            if (!$username) {
                $username = self::generateUsername($s['domain'], $provider);
                db()->update('services', ['provider_username' => $username], 'id = :id', ['id' => $serviceId]);
            }
            $existing = $provider->getAccount($username);
            if ($existing->success) {
                if ($existing->data['domain'] !== strtolower($s['domain'])) {
                    throw new \RuntimeException("Username '{$username}' already exists on the server for a different domain ({$existing->data['domain']}).");
                }
                $account = $existing->data; // created by a previous attempt — adopt it
            } elseif ($existing->notFound) {
                $create = $provider->createAccount([
                    'username' => $username,
                    'domain' => $s['domain'],
                    'package' => $package,
                    'email' => $s['customer_email'],
                    'password' => self::randomPassword(),
                ]);
                if (!$create->success) {
                    throw new \RuntimeException($create->message);
                }
                $account = $create->data;
            } else {
                throw new \RuntimeException($existing->message);
            }
            db()->update('services', [
                'status' => 'active',
                'provider_package' => $account['package'] ?? $package,
                'provider_status' => !empty($account['suspended']) ? 'suspended' : 'active',
                'sync_status' => 'in_sync',
                'last_synced_at' => now(),
                'last_error' => null,
                'activation_date' => $s['activation_date'] ?: today(),
                'next_due_date' => $s['next_due_date'] ?: BillingCycle::addPeriod(today(), $s['billing_cycle']),
                'updated_at' => now(),
            ], 'id = :id', ['id' => $serviceId]);
            self::log('provision', $serviceId, true, "Account {$username} created for {$s['domain']}", $automated);
            $providerRow = db()->fetch('SELECT nameservers FROM hosting_providers WHERE id = ?', [$s['provider_id']]);
            NotificationService::notifyCustomer((int) $s['customer_id'], 'service_activated', [
                'domain' => $s['domain'], 'username' => $username, 'plan' => $s['plan_name'],
                'nameservers' => $providerRow['nameservers'] ?? '',
                'service_url' => absolute_url('/client/services/' . $serviceId),
            ], 'service_activated:' . $serviceId);
            return self::result(true, "Hosting account '{$username}' created and verified.");
        } catch (\Throwable $e) {
            db()->update('services', ['status' => 'provisioning_failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()], 'id = :id', ['id' => $serviceId]);
            self::log('provision', $serviceId, false, $e->getMessage(), $automated);
            NotificationService::alertStaff('Provisioning failed for ' . $s['domain'], $e->getMessage(), 'provision_failed:' . $serviceId . ':' . ((int) $s['provision_attempts'] + 1));
            return self::result(false, 'Provisioning failed: ' . $e->getMessage());
        }
    }

    public static function suspend(int $serviceId, string $reason, string $type = 'manual', bool $automated = false): array
    {
        $s = self::service($serviceId);
        if ($s['status'] !== 'active') {
            return self::result(false, 'Only active services can be suspended.');
        }
        try {
            if (!$s['provider_username']) {
                throw new \RuntimeException('Service has no hosting account linked.');
            }
            $r = self::provider($s)->suspendAccount($s['provider_username'], $reason);
            if (!$r->success) {
                throw new \RuntimeException($r->message);
            }
            db()->update('services', [
                'status' => 'suspended', 'suspension_reason' => $reason, 'suspension_type' => $type,
                'suspended_at' => now(), 'provider_status' => 'suspended', 'last_error' => null, 'updated_at' => now(),
            ], 'id = :id', ['id' => $serviceId]);
            self::log('suspend', $serviceId, true, "Suspended: {$reason}", $automated);
            NotificationService::notifyCustomer((int) $s['customer_id'], 'service_suspended', ['domain' => $s['domain'], 'reason' => $reason], 'service_suspended:' . $serviceId . ':' . date('YmdHi'));
            return self::result(true, 'Service suspended and verified on the server.');
        } catch (\Throwable $e) {
            db()->update('services', ['last_error' => mb_substr($e->getMessage(), 0, 1000)], 'id = :id', ['id' => $serviceId]);
            self::log('suspend', $serviceId, false, $e->getMessage(), $automated);
            return self::result(false, 'Suspension failed: ' . $e->getMessage());
        }
    }

    public static function unsuspend(int $serviceId, string $note = '', bool $automated = false): array
    {
        $s = self::service($serviceId);
        if ($s['status'] !== 'suspended') {
            return self::result(false, 'Service is not suspended.');
        }
        if ($automated && $s['suspension_type'] !== 'billing') {
            return self::result(false, 'Service was suspended manually; automatic reactivation is not allowed.');
        }
        try {
            $r = self::provider($s)->unsuspendAccount((string) $s['provider_username']);
            if (!$r->success) {
                throw new \RuntimeException($r->message);
            }
            db()->update('services', [
                'status' => 'active', 'suspension_reason' => null, 'suspension_type' => null, 'suspended_at' => null,
                'termination_flagged_at' => null, 'provider_status' => 'active', 'last_error' => null, 'updated_at' => now(),
            ], 'id = :id', ['id' => $serviceId]);
            self::log('unsuspend', $serviceId, true, 'Reactivated' . ($note ? ": {$note}" : ''), $automated);
            NotificationService::notifyCustomer((int) $s['customer_id'], 'service_reactivated', ['domain' => $s['domain']], 'service_reactivated:' . $serviceId . ':' . date('YmdHi'));
            return self::result(true, 'Service reactivated and verified on the server.');
        } catch (\Throwable $e) {
            db()->update('services', ['last_error' => mb_substr($e->getMessage(), 0, 1000)], 'id = :id', ['id' => $serviceId]);
            self::log('unsuspend', $serviceId, false, $e->getMessage(), $automated);
            if ($automated) {
                NotificationService::alertStaff('Automatic reactivation failed for ' . $s['domain'], $e->getMessage(), 'unsuspend_failed:' . $serviceId . ':' . today());
            }
            return self::result(false, 'Reactivation failed: ' . $e->getMessage());
        }
    }

    /** Change the service's plan/package. $price null keeps the plan price for the service cycle. */
    public static function changePlan(int $serviceId, int $planId, ?float $price = null): array
    {
        $s = self::service($serviceId);
        $plan = db()->fetch('SELECT * FROM hosting_plans WHERE id = ?', [$planId]);
        if (!$plan) {
            return self::result(false, 'Plan not found.');
        }
        $price ??= BillingCycle::planPrice($plan, $s['billing_cycle']) ?? (float) $s['price'];
        try {
            if ($s['provider_username'] && in_array($s['status'], ['active', 'suspended'], true)) {
                if (!$plan['provider_package']) {
                    throw new \RuntimeException('The new plan has no provider package mapped.');
                }
                $r = self::provider($s)->changePackage($s['provider_username'], $plan['provider_package']);
                if (!$r->success) {
                    throw new \RuntimeException($r->message);
                }
            }
            db()->update('services', [
                'plan_id' => $planId, 'provider_package' => $plan['provider_package'], 'price' => round($price, 2),
                'last_error' => null, 'updated_at' => now(),
            ], 'id = :id', ['id' => $serviceId]);
            BillingService::repriceOpenRenewal($serviceId);
            self::log('change_plan', $serviceId, true, "Plan changed from {$s['plan_name']} to {$plan['name']}", !Auth::check());
            return self::result(true, "Plan changed to {$plan['name']}.");
        } catch (\Throwable $e) {
            db()->update('services', ['last_error' => mb_substr($e->getMessage(), 0, 1000)], 'id = :id', ['id' => $serviceId]);
            self::log('change_plan', $serviceId, false, $e->getMessage(), !Auth::check());
            return self::result(false, 'Plan change failed: ' . $e->getMessage());
        }
    }

    /** Permanently remove the hosting account (never automatic). */
    public static function terminate(int $serviceId): array
    {
        $s = self::service($serviceId);
        if (in_array($s['status'], ['terminated', 'cancelled'], true)) {
            return self::result(false, 'Service is already ' . $s['status'] . '.');
        }
        try {
            if ($s['provider_username'] && $s['provider_id']) {
                $r = self::provider($s)->terminateAccount($s['provider_username']);
                if (!$r->success) {
                    throw new \RuntimeException($r->message);
                }
            }
            db()->transaction(function ($db) use ($serviceId) {
                $db->update('services', ['status' => 'terminated', 'terminated_at' => now(), 'provider_status' => 'removed', 'updated_at' => now()], 'id = :id', ['id' => $serviceId]);
                // Cancel unpaid invoices that only bill this service.
                $invoices = $db->fetchAll(
                    "SELECT DISTINCT i.id FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id
                     WHERE ii.service_id = ? AND i.status IN ('draft','pending','overdue') AND i.amount_paid = 0
                     AND NOT EXISTS (SELECT 1 FROM invoice_items x WHERE x.invoice_id = i.id AND (x.service_id IS NULL OR x.service_id <> ?))",
                    [$serviceId, $serviceId]
                );
                foreach ($invoices as $inv) {
                    InvoiceService::cancel((int) $inv['id'], 'Service terminated');
                }
            });
            self::log('terminate', $serviceId, true, 'Service terminated', false);
            return self::result(true, 'Service terminated' . ($s['provider_username'] ? ' and removal verified on the server.' : '.'));
        } catch (\Throwable $e) {
            self::log('terminate', $serviceId, false, $e->getMessage(), false);
            return self::result(false, 'Termination failed: ' . $e->getMessage());
        }
    }

    /** Single sign-on URL for cPanel/webmail. */
    public static function loginUrl(int $serviceId, string $service = 'cpanel', ?string $app = null): array
    {
        $s = self::service($serviceId);
        if (!$s['provider_username'] || !in_array($s['status'], ['active'], true)) {
            return self::result(false, 'The control panel is only available for active services.');
        }
        try {
            $r = self::provider($s)->createLoginSession($s['provider_username'], $service, $app);
            return $r->success ? ['success' => true, 'message' => 'OK', 'url' => $r->data['url']] : self::result(false, $r->message);
        } catch (\Throwable $e) {
            return self::result(false, $e->getMessage());
        }
    }

    /** Link an existing provider account to a service and verify it exists. */
    public static function linkAccount(int $serviceId, int $providerId, string $username): array
    {
        $s = self::service($serviceId);
        $row = db()->fetch('SELECT * FROM hosting_providers WHERE id = ?', [$providerId]);
        if (!$row) {
            return self::result(false, 'Provider not found.');
        }
        $other = db()->fetchColumn('SELECT id FROM services WHERE provider_id = ? AND provider_username = ? AND id <> ?', [$providerId, $username, $serviceId]);
        if ($other) {
            return self::result(false, "Account '{$username}' is already linked to service #{$other}.");
        }
        $r = ProviderRegistry::make($row)->getAccount($username);
        if (!$r->success) {
            return self::result(false, $r->message);
        }
        $acct = $r->data;
        $update = [
            'provider_id' => $providerId, 'provider_username' => $acct['username'], 'provider_package' => $acct['package'],
            'provider_status' => $acct['suspended'] ? 'suspended' : 'active', 'disk_used_mb' => $acct['disk_used_mb'], 'disk_limit_mb' => $acct['disk_limit_mb'],
            'last_synced_at' => now(), 'updated_at' => now(),
        ];
        if (in_array($s['status'], ['pending_payment', 'provisioning', 'provisioning_failed'], true)) {
            $update['status'] = $acct['suspended'] ? 'suspended' : 'active';
            $update['activation_date'] = $s['activation_date'] ?: (substr((string) $acct['created_at'], 0, 10) ?: today());
            $update['next_due_date'] = $s['next_due_date'] ?: BillingCycle::addPeriod(today(), $s['billing_cycle']);
            if ($acct['suspended']) {
                $update['suspension_type'] = 'manual';
                $update['suspension_reason'] = $acct['suspend_reason'] ?: 'Suspended on server';
                $update['suspended_at'] = now();
            }
        }
        db()->update('services', $update, 'id = :id', ['id' => $serviceId]);
        audit('service.account_linked', 'service', $serviceId, ['provider_id' => $providerId, 'username' => $acct['username']]);
        self::syncService($serviceId);
        return self::result(true, "Linked to account '{$acct['username']}' ({$acct['domain']}).");
    }

    /** Compare a single service with the provider. */
    public static function syncService(int $serviceId): array
    {
        $s = self::service($serviceId);
        if (!$s['provider_id'] || !$s['provider_username']) {
            return self::result(false, 'Service is not linked to a hosting account.');
        }
        try {
            $r = self::provider($s)->getAccount($s['provider_username']);
            if (!$r->success && !$r->notFound) {
                throw new \RuntimeException($r->message);
            }
            [$status, $message] = SyncService::compare($s, $r->success ? $r->data : null);
            SyncService::store($s, $r->success ? $r->data : null, $status, $message);
            return self::result(true, $message);
        } catch (\Throwable $e) {
            db()->update('services', ['sync_status' => 'error', 'sync_message' => mb_substr($e->getMessage(), 0, 1000), 'last_synced_at' => now()], 'id = :id', ['id' => $serviceId]);
            return self::result(false, 'Sync failed: ' . $e->getMessage());
        }
    }

    private static function randomPassword(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%^*-_=+';
        $out = '';
        for ($i = 0; $i < 20; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out . random_int(10, 99) . 'Aa!';
    }
}
