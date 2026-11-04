<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Hosting\ProviderRegistry;

/**
 * Compares HostFlow's business records with the hosting infrastructure.
 * Discrepancies are recorded and reported — never silently overwritten.
 */
final class SyncService
{
    private const SYNCED_STATUSES = ['active', 'suspended', 'cancellation_pending', 'provisioning_failed'];

    /** Returns [sync_status, message]. */
    public static function compare(array $service, ?array $account): array
    {
        if ($account === null) {
            return ['missing', "Account '{$service['provider_username']}' was not found on the hosting server."];
        }
        $issues = [];
        $expectedPackage = $service['plan_package'] ?? $service['provider_package'];
        if ($expectedPackage && $account['package'] !== $expectedPackage) {
            $issues[] = "package on server is '{$account['package']}' but plan expects '{$expectedPackage}'";
        }
        if ($service['status'] === 'active' && $account['suspended']) {
            $issues[] = 'account is suspended on the server but active in HostFlow' . ($account['suspend_reason'] ? " (reason: {$account['suspend_reason']})" : '');
        }
        if ($service['status'] === 'suspended' && !$account['suspended']) {
            $issues[] = 'account is active on the server but suspended in HostFlow';
        }
        if ($account['domain'] !== strtolower((string) $service['domain'])) {
            $issues[] = "primary domain on server is '{$account['domain']}'";
        }
        return $issues ? ['mismatch', ucfirst(implode('; ', $issues)) . '.'] : ['in_sync', 'In sync with the hosting server.'];
    }

    public static function store(array $service, ?array $account, string $status, string $message): void
    {
        $update = ['sync_status' => $status, 'sync_message' => mb_substr($message, 0, 1000), 'last_synced_at' => now()];
        if ($account) {
            $update += [
                'provider_status' => $account['suspended'] ? 'suspended' : 'active',
                'provider_package' => $account['package'],
                'disk_used_mb' => $account['disk_used_mb'],
                'disk_limit_mb' => $account['disk_limit_mb'],
            ];
        } else {
            $update['provider_status'] = 'missing';
        }
        db()->update('services', $update, 'id = :id', ['id' => $service['id']]);
        // Log only when the state changes, to keep the automation log readable.
        if ($status !== 'in_sync' && ($service['sync_status'] !== $status || $service['sync_message'] !== $message)) {
            AutomationLog::record('sync', 'discrepancy', 'failed', $service['domain'] . ': ' . $message, 'service', (int) $service['id']);
        }
    }

    /**
     * Synchronise all services on a provider. Returns summary
     * ['checked' => n, 'in_sync' => n, 'issues' => n, 'unmanaged' => [accounts not linked to HostFlow]].
     */
    public static function syncProvider(array $provider): array
    {
        $result = ProviderRegistry::make($provider)->listAccounts();
        if (!$result->success) {
            AutomationLog::record('sync', 'list_accounts', 'failed', "{$provider['name']}: {$result->message}", 'provider', (int) $provider['id']);
            throw new \RuntimeException($result->message);
        }
        $accounts = [];
        foreach ($result->data as $acct) {
            $accounts[strtolower($acct['username'])] = $acct;
        }
        $placeholders = implode(',', array_fill(0, count(self::SYNCED_STATUSES), '?'));
        $services = db()->fetchAll(
            "SELECT s.*, p.provider_package AS plan_package FROM services s JOIN hosting_plans p ON p.id = s.plan_id
             WHERE s.provider_id = ? AND s.provider_username IS NOT NULL AND s.status IN ({$placeholders})",
            array_merge([(int) $provider['id']], self::SYNCED_STATUSES)
        );
        $summary = ['checked' => 0, 'in_sync' => 0, 'issues' => 0, 'unmanaged' => []];
        $linked = [];
        foreach (db()->fetchAll('SELECT provider_username FROM services WHERE provider_id = ? AND provider_username IS NOT NULL', [(int) $provider['id']]) as $row) {
            $linked[strtolower($row['provider_username'])] = true;
        }
        foreach ($services as $s) {
            $acct = $accounts[strtolower($s['provider_username'])] ?? null;
            [$status, $message] = self::compare($s, $acct);
            self::store($s, $acct, $status, $message);
            $summary['checked']++;
            $status === 'in_sync' ? $summary['in_sync']++ : $summary['issues']++;
        }
        foreach ($accounts as $username => $acct) {
            if (!isset($linked[$username])) {
                $summary['unmanaged'][] = $acct;
            }
        }
        db()->update('hosting_providers', ['last_synced_at' => now()], 'id = :id', ['id' => $provider['id']]);
        return $summary;
    }
}
