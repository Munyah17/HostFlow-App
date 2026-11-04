<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Plugins\PluginManager;
use App\Services\Notifications\NotificationService;
use App\Services\Payments\GatewayRegistry;

/**
 * Scheduled automation, executed by cron/run.php (cPanel cron, every 5 minutes).
 * A file lock prevents overlapping runs; every action is written to automation_logs.
 */
final class AutomationService
{
    private array $summary = [];
    private bool $hadErrors = false;

    public const TASKS = [
        'mark_overdue' => 'Mark overdue invoices',
        'generate_renewals' => 'Generate renewal invoices',
        'renewal_reminders' => 'Send renewal reminders',
        'overdue_reminders' => 'Send overdue reminders',
        'suspend_overdue' => 'Suspend overdue services',
        'flag_termination' => 'Flag services for termination review',
        'retry_provisioning' => 'Provision paid services / retry failures',
        'apply_pending_changes' => 'Apply paid plan changes',
        'domain_renewals' => 'Generate domain renewal invoices',
        'domain_reminders' => 'Send domain expiry reminders',
        'expire_domains' => 'Mark expired domains',
        'reactivate_paid' => 'Reactivate paid services',
        'reconcile_payments' => 'Verify pending online payments',
        'sync_hosting' => 'Synchronise hosting accounts',
        'plugin_tasks' => 'Plugin scheduled tasks',
        'send_notifications' => 'Send queued notifications',
        'cleanup' => 'Housekeeping',
    ];

    /**
     * Run all tasks. Returns ['status' => ..., 'summary' => [...]] or null if another run is active.
     */
    public static function run(?array $only = null): ?array
    {
        $lockFile = BASE_PATH . '/storage/cache/cron.lock';
        @mkdir(dirname($lockFile), 0775, true);
        $lock = fopen($lockFile, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return null;
        }
        $self = new self();
        $runId = db()->insert('cron_runs', ['started_at' => now(), 'status' => 'running']);
        AutomationLog::$runId = $runId;
        try {
            foreach (array_keys(self::TASKS) as $task) {
                if ($only && !in_array($task, $only, true)) {
                    continue;
                }
                $self->runTask($task);
            }
            $status = $self->hadErrors ? 'completed_with_errors' : 'completed';
        } catch (\Throwable $e) {
            $status = 'failed';
            $self->summary['fatal'] = $e->getMessage();
            Logger::error('Cron run failed: ' . $e->getMessage(), [], 'automation');
        } finally {
            db()->update('cron_runs', ['finished_at' => now(), 'status' => $status ?? 'failed', 'summary' => json_encode($self->summary)], 'id = :id', ['id' => $runId]);
            AutomationLog::$runId = null;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return ['run_id' => $runId, 'status' => $status, 'summary' => $self->summary];
    }

    private function runTask(string $task): void
    {
        $method = lcfirst(str_replace('_', '', ucwords($task, '_')));
        try {
            $this->summary[$task] = $this->$method();
        } catch (\Throwable $e) {
            $this->hadErrors = true;
            $this->summary[$task] = 'error: ' . $e->getMessage();
            AutomationLog::record($task, 'task_error', 'failed', $e->getMessage());
            Logger::error("Automation task {$task} failed: " . $e->getMessage(), ['trace' => substr($e->getTraceAsString(), 0, 2000)], 'automation');
        }
    }

    private function fail(): void
    {
        $this->hadErrors = true;
    }

    // ------------------------------------------------------------------ tasks

    private function markOverdue(): int
    {
        $n = InvoiceService::markOverdue();
        if ($n) {
            AutomationLog::record('mark_overdue', 'invoices_overdue', 'success', "{$n} invoice(s) marked overdue");
        }
        return $n;
    }

    private function generateRenewals(): int
    {
        if (!Settings::bool('auto_invoice_enabled')) {
            return 0;
        }
        $windowEnd = date('Y-m-d', strtotime('+' . Settings::int('renewal_invoice_days') . ' days'));
        $services = db()->fetchAll("SELECT id FROM services WHERE status IN ('active','suspended') AND next_due_date IS NOT NULL AND next_due_date <= ?", [$windowEnd]);
        $count = 0;
        foreach ($services as $s) {
            $before = (int) db()->fetchColumn('SELECT COUNT(*) FROM invoices');
            try {
                $invoiceId = BillingService::generateRenewal((int) $s['id']);
            } catch (\Throwable $e) {
                $this->fail();
                AutomationLog::record('generate_renewals', 'renewal_invoice', 'failed', $e->getMessage(), 'service', (int) $s['id']);
                continue;
            }
            if ($invoiceId && (int) db()->fetchColumn('SELECT COUNT(*) FROM invoices') > $before) {
                $count++;
                AutomationLog::record('generate_renewals', 'renewal_invoice', 'success', 'Invoice ' . db()->fetchColumn('SELECT number FROM invoices WHERE id = ?', [$invoiceId]) . ' generated', 'service', (int) $s['id']);
            }
        }
        return $count;
    }

    /** Unpaid renewal invoices with their service. */
    private function openRenewals(string $extraWhere, array $params = []): array
    {
        return db()->fetchAll(
            "SELECT i.*, s.id AS service_id, s.domain, s.customer_id AS svc_customer
             FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id AND ii.item_type = 'renewal'
             JOIN services s ON s.id = ii.service_id
             WHERE i.status IN ('pending','overdue') AND s.status IN ('active','suspended') {$extraWhere}
             GROUP BY i.id, s.id",
            $params
        );
    }

    private function renewalReminders(): int
    {
        $days = Settings::intList('reminder_days_before');
        sort($days);
        $sent = 0;
        foreach ($this->openRenewals('AND i.due_date >= CURDATE()') as $inv) {
            $until = (int) ((strtotime($inv['due_date']) - strtotime(today())) / 86400);
            $data = $this->invoiceData($inv);
            if ($until === 0) {
                if (Settings::bool('reminder_on_due_date')) {
                    $sent += NotificationService::notifyCustomer((int) $inv['customer_id'], 'invoice_due', $data, "invoice_due:{$inv['id']}");
                }
                continue;
            }
            // Smallest configured threshold that has been reached (catches up if cron missed a day).
            foreach ($days as $threshold) {
                if ($until <= $threshold) {
                    $sent += NotificationService::notifyCustomer((int) $inv['customer_id'], 'renewal_reminder', $data, "renewal_reminder:{$inv['id']}:{$threshold}");
                    break;
                }
            }
        }
        return $sent;
    }

    private function overdueReminders(): int
    {
        $days = Settings::intList('overdue_reminder_days');
        rsort($days);
        $grace = Settings::int('suspend_grace_days');
        $sent = 0;
        foreach (db()->fetchAll("SELECT * FROM invoices WHERE status = 'overdue'") as $inv) {
            $over = (int) ((strtotime(today()) - strtotime($inv['due_date'])) / 86400);
            foreach ($days as $threshold) {
                if ($over >= $threshold) {
                    $data = $this->invoiceData($inv) + ['suspend_date' => Settings::bool('auto_suspend_enabled') ? date_fmt(date('Y-m-d', strtotime($inv['due_date'] . " +{$grace} days"))) : ''];
                    $sent += NotificationService::notifyCustomer((int) $inv['customer_id'], 'overdue_reminder', $data, "overdue_reminder:{$inv['id']}:{$threshold}");
                    break;
                }
            }
        }
        return $sent;
    }

    private function suspendOverdue(): int
    {
        if (!Settings::bool('auto_suspend_enabled')) {
            return 0;
        }
        $cutoff = date('Y-m-d', strtotime('-' . Settings::int('suspend_grace_days') . ' days'));
        $rows = db()->fetchAll(
            "SELECT DISTINCT s.id, i.number FROM services s
             JOIN invoice_items ii ON ii.service_id = s.id AND ii.item_type = 'renewal'
             JOIN invoices i ON i.id = ii.invoice_id
             WHERE s.status = 'active' AND i.status = 'overdue' AND i.due_date <= ?",
            [$cutoff]
        );
        $count = 0;
        foreach ($rows as $row) {
            // Re-verify the billing condition immediately before suspending.
            if (!BillingService::hasBlockingOverdue((int) $row['id'])) {
                continue;
            }
            $r = ProvisioningService::suspend((int) $row['id'], "Overdue invoice {$row['number']}", 'billing', automated: true);
            $r['success'] ? $count++ : $this->fail();
        }
        return $count;
    }

    private function flagTermination(): int
    {
        $days = Settings::int('termination_flag_days');
        if ($days <= 0) {
            return 0;
        }
        $rows = db()->fetchAll(
            "SELECT id, domain FROM services WHERE status = 'suspended' AND suspension_type = 'billing' AND termination_flagged_at IS NULL AND suspended_at <= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        foreach ($rows as $s) {
            db()->update('services', ['termination_flagged_at' => now()], 'id = :id', ['id' => $s['id']]);
            AutomationLog::record('flag_termination', 'flagged', 'info', "{$s['domain']} has been suspended for {$days}+ days and is flagged for termination review. No account was deleted.", 'service', (int) $s['id']);
        }
        if ($rows) {
            NotificationService::alertStaff('Services flagged for termination review', implode("\n", array_column($rows, 'domain')), 'termination_flags:' . today());
        }
        return count($rows);
    }

    private function retryProvisioning(): int
    {
        if (!Settings::bool('auto_provision_enabled')) {
            return 0;
        }
        $max = Settings::int('provision_max_attempts');
        $rows = db()->fetchAll(
            "SELECT s.id FROM services s JOIN hosting_plans p ON p.id = s.plan_id
             WHERE s.provider_id IS NOT NULL AND p.provider_package IS NOT NULL AND (
               (s.status = 'provisioning' AND s.updated_at <= DATE_SUB(NOW(), INTERVAL 2 MINUTE))
               OR (s.status = 'provisioning_failed' AND s.provision_attempts < ? AND s.updated_at <= DATE_SUB(NOW(), INTERVAL (s.provision_attempts * 10) MINUTE))
             ) LIMIT 10",
            [$max]
        );
        $ok = 0;
        foreach ($rows as $row) {
            $r = ProvisioningService::provision((int) $row['id']);
            $r['success'] ? $ok++ : $this->fail();
        }
        return $ok;
    }

    private function applyPendingChanges(): int
    {
        $rows = db()->fetchAll(
            "SELECT ii.* FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
             WHERE ii.item_type = 'upgrade' AND ii.applied_at IS NULL AND i.status = 'paid' LIMIT 10"
        );
        $done = 0;
        foreach ($rows as $item) {
            $meta = json_decode((string) $item['meta'], true) ?: [];
            if (empty($meta['new_plan_id']) || !$item['service_id']) {
                continue;
            }
            $r = ProvisioningService::changePlan((int) $item['service_id'], (int) $meta['new_plan_id'], (float) ($meta['new_price'] ?? 0) ?: null);
            if ($r['success']) {
                db()->update('invoice_items', ['applied_at' => now()], 'id = :id', ['id' => $item['id']]);
                $done++;
            } else {
                $this->fail();
            }
        }
        return $done;
    }

    private function domainRenewals(): int
    {
        if (!Settings::bool('auto_invoice_enabled')) {
            return 0;
        }
        $count = 0;
        foreach (DomainService::renewalsDue() as $d) {
            $before = (int) db()->fetchColumn('SELECT COUNT(*) FROM invoices');
            try {
                $invoiceId = DomainService::generateRenewal((int) $d['id']);
            } catch (\Throwable $e) {
                $this->fail();
                AutomationLog::record('domain_renewals', 'renewal_invoice', 'failed', $e->getMessage(), 'domain', (int) $d['id']);
                continue;
            }
            if ($invoiceId && (int) db()->fetchColumn('SELECT COUNT(*) FROM invoices') > $before) {
                $count++;
                AutomationLog::record('domain_renewals', 'renewal_invoice', 'success', 'Invoice ' . db()->fetchColumn('SELECT number FROM invoices WHERE id = ?', [$invoiceId]) . ' generated', 'domain', (int) $d['id']);
            }
        }
        return $count;
    }

    private function domainReminders(): int
    {
        $days = Settings::intList('domain_reminder_days');
        sort($days);
        $sent = 0;
        foreach (DomainService::expiring() as $d) {
            $until = (int) ((strtotime($d['expiry_date']) - strtotime(today())) / 86400);
            $data = [
                'domain' => $d['domain'],
                'expiry_date' => date_fmt($d['expiry_date']),
                'domain_url' => absolute_url('/client/domains/' . $d['id']),
            ];
            if ($until === 0) {
                if (Settings::bool('reminder_on_due_date')) {
                    $sent += NotificationService::notifyCustomer((int) $d['customer_id'], 'domain_renewal_reminder', $data, "domain_reminder:{$d['id']}:{$d['expiry_date']}:due");
                }
                continue;
            }
            foreach ($days as $threshold) {
                if ($until <= $threshold) {
                    $sent += NotificationService::notifyCustomer((int) $d['customer_id'], 'domain_renewal_reminder', $data, "domain_reminder:{$d['id']}:{$d['expiry_date']}:{$threshold}");
                    break;
                }
            }
        }
        return $sent;
    }

    private function expireDomains(): int
    {
        return DomainService::expireOverdue();
    }

    private function reactivatePaid(): int
    {
        if (!Settings::bool('auto_unsuspend_enabled')) {
            return 0;
        }
        $rows = db()->fetchAll("SELECT id FROM services WHERE status = 'suspended' AND suspension_type = 'billing'");
        $count = 0;
        foreach ($rows as $row) {
            $unpaid = db()->fetchColumn(
                "SELECT COUNT(*) FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                 WHERE ii.service_id = ? AND ii.item_type = 'renewal' AND i.status IN ('pending','overdue') AND i.due_date < CURDATE()",
                [$row['id']]
            );
            if ((int) $unpaid === 0) {
                $r = ProvisioningService::unsuspend((int) $row['id'], 'Outstanding invoices paid', automated: true);
                $r['success'] ? $count++ : $this->fail();
            }
        }
        return $count;
    }

    private function reconcilePayments(): int
    {
        $rows = db()->fetchAll(
            "SELECT id, gateway FROM payments WHERE status = 'pending' AND gateway <> 'manual' AND poll_url IS NOT NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
             AND (last_checked_at IS NULL OR last_checked_at <= DATE_SUB(NOW(), INTERVAL 4 MINUTE)) LIMIT 25"
        );
        $completed = 0;
        foreach ($rows as $row) {
            if (!GatewayRegistry::get($row['gateway'])) {
                continue;
            }
            try {
                $p = PaymentService::verify((int) $row['id']);
                if ($p['status'] === 'completed') {
                    $completed++;
                    AutomationLog::record('reconcile_payments', 'payment_verified', 'success', "Payment {$p['reference']} confirmed by gateway", 'payment', (int) $p['id']);
                }
            } catch (\Throwable $e) {
                $this->fail();
                AutomationLog::record('reconcile_payments', 'verify', 'failed', $e->getMessage(), 'payment', (int) $row['id']);
            }
        }
        // Abandoned checkouts: mark cancelled after 3 days without confirmation.
        db()->query("UPDATE payments SET status = 'cancelled', note = 'Checkout abandoned (no confirmation within 3 days)', updated_at = NOW() WHERE status = 'pending' AND gateway <> 'manual' AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
        return $completed;
    }

    private function syncHosting(): string
    {
        $interval = max(5, Settings::int('sync_interval_minutes'));
        $out = [];
        foreach (db()->fetchAll("SELECT * FROM hosting_providers WHERE status = 'active' AND (last_synced_at IS NULL OR last_synced_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE))", [$interval]) as $provider) {
            try {
                $sum = SyncService::syncProvider($provider);
                $out[] = "{$provider['name']}: {$sum['checked']} checked, {$sum['issues']} issue(s), " . count($sum['unmanaged']) . ' unmanaged';
                AutomationLog::record('sync_hosting', 'sync', $sum['issues'] ? 'info' : 'success', end($out), 'provider', (int) $provider['id']);
            } catch (\Throwable $e) {
                $this->fail();
                $out[] = "{$provider['name']}: failed";
                db()->update('hosting_providers', ['last_synced_at' => now()], 'id = :id', ['id' => $provider['id']]);
            }
        }
        return $out ? implode('; ', $out) : 'not due';
    }

    private function pluginTasks(): array
    {
        $results = [];
        foreach (PluginManager::instance()->scheduledTasks() as $key => $task) {
            try {
                ($task['callback'])();
                $results[$key] = 'ok';
            } catch (\Throwable $e) {
                $this->fail();
                $results[$key] = 'error';
                AutomationLog::record('plugin:' . $task['plugin'], $task['name'], 'failed', $e->getMessage());
            }
        }
        return $results;
    }

    private function sendNotifications(): string
    {
        [$sent, $failed] = NotificationService::processQueue(100);
        if ($failed) {
            AutomationLog::record('send_notifications', 'delivery', 'failed', "{$failed} notification(s) could not be delivered");
        }
        return "{$sent} sent, {$failed} failed";
    }

    private function cleanup(): string
    {
        db()->query('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        db()->query('DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
        db()->query('DELETE FROM cron_runs WHERE started_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
        db()->query("DELETE FROM automation_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)");
        // Recover runs left "running" by a crashed process.
        db()->query("UPDATE cron_runs SET status = 'failed', finished_at = NOW(), summary = 'Process terminated unexpectedly' WHERE status = 'running' AND started_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        return 'ok';
    }

    private function invoiceData(array $inv): array
    {
        return [
            'invoice_number' => $inv['number'],
            'total' => money((float) $inv['total'] - (float) $inv['amount_paid'], $inv['currency']),
            'due_date' => date_fmt($inv['due_date']),
            'domain' => $inv['domain'] ?? '',
            'invoice_url' => absolute_url('/client/invoices/' . $inv['id']),
        ];
    }
}
