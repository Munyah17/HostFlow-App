<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Notifications\NotificationService;

/**
 * Orders, recurring renewals, plan changes and the effects of paid invoices.
 */
final class BillingService
{
    /**
     * Create a new service order (service in pending_payment + invoice).
     * Returns ['service_id' => int, 'invoice_id' => int].
     */
    public static function createOrder(int $customerId, int $planId, string $domain, string $cycle, array $options = []): array
    {
        $plan = db()->fetch('SELECT * FROM hosting_plans WHERE id = ?', [$planId]);
        if (!$plan || ($plan['status'] !== 'active' && empty($options['allow_hidden']))) {
            throw new \InvalidArgumentException('The selected plan is not available.');
        }
        if (!BillingCycle::isValid($cycle)) {
            throw new \InvalidArgumentException('Invalid billing cycle.');
        }
        $price = isset($options['price']) && $options['price'] !== '' ? round((float) $options['price'], 2) : BillingCycle::planPrice($plan, $cycle);
        if ($price === null) {
            throw new \InvalidArgumentException('The selected plan is not offered with a ' . cycle_label($cycle) . ' billing cycle.');
        }
        $domain = strtolower(trim($domain));
        $active = db()->fetchColumn("SELECT COUNT(*) FROM services WHERE domain = ? AND type = ? AND status NOT IN ('terminated','cancelled')", [$domain, $plan['type']]);
        if ($active) {
            throw new \InvalidArgumentException("A {$plan['type']} hosting service for {$domain} already exists.");
        }

        return db()->transaction(function ($db) use ($customerId, $plan, $domain, $cycle, $price, $options) {
            $serviceId = $db->insert('services', [
                'customer_id' => $customerId,
                'plan_id' => $plan['id'],
                'type' => $plan['type'],
                'domain' => $domain,
                'provider_id' => $plan['provider_id'],
                'billing_cycle' => $cycle,
                'price' => $price,
                'status' => 'pending_payment',
                'provider_package' => $plan['provider_package'],
                'notes' => $options['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $items = [[
                'service_id' => $serviceId,
                'item_type' => 'new_service',
                'description' => sprintf('%s hosting — %s (%s)', $plan['name'], $domain, cycle_label($cycle)),
                'unit_price' => $price,
            ]];
            if ((float) $plan['setup_fee'] > 0) {
                $items[] = ['service_id' => $serviceId, 'item_type' => 'setup', 'description' => 'Setup fee — ' . $domain, 'unit_price' => (float) $plan['setup_fee']];
            }
            $invoiceId = InvoiceService::create($customerId, $items, ['due_date' => today()]);
            return ['service_id' => $serviceId, 'invoice_id' => $invoiceId];
        });
    }

    /**
     * Generate the renewal invoice for a service's current period if one does not already exist.
     * Idempotent: the service row is locked and existing (non-cancelled) renewal items are checked.
     * Returns the invoice id (new or existing) or null when not applicable.
     */
    public static function generateRenewal(int $serviceId, bool $force = false, ?bool &$createdOut = null): ?int
    {
        $created = false;
        $invoiceId = db()->transaction(function ($db) use ($serviceId, $force, &$created) {
            $s = $db->fetch('SELECT s.*, p.name AS plan_name FROM services s JOIN hosting_plans p ON p.id = s.plan_id WHERE s.id = ? FOR UPDATE', [$serviceId]);
            if (!$s || !in_array($s['status'], ['active', 'suspended'], true) || !$s['next_due_date']) {
                return null;
            }
            $windowEnd = date('Y-m-d', strtotime('+' . Settings::int('renewal_invoice_days') . ' days'));
            if (!$force && $s['next_due_date'] > $windowEnd) {
                return null;
            }
            $existing = $db->fetchColumn(
                "SELECT ii.invoice_id FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                 WHERE ii.service_id = ? AND ii.item_type = 'renewal' AND ii.period_start = ? AND i.status <> 'cancelled' LIMIT 1",
                [$serviceId, $s['next_due_date']]
            );
            if ($existing) {
                return (int) $existing;
            }
            $start = $s['next_due_date'];
            $end = BillingCycle::addPeriod($start, $s['billing_cycle']);
            $created = true;
            return InvoiceService::create((int) $s['customer_id'], [[
                'service_id' => $serviceId,
                'item_type' => 'renewal',
                'description' => sprintf('%s renewal — %s (%s to %s)', $s['plan_name'], $s['domain'], date_fmt($start), date_fmt(date('Y-m-d', strtotime($end . ' -1 day')))),
                'unit_price' => (float) $s['price'],
                'period_start' => $start,
                'period_end' => $end,
            ]], ['due_date' => max($start, today()), 'created_by' => null]);
        });
        $createdOut = $created;
        if ($invoiceId && $created) {
            InvoiceService::notifyCreated($invoiceId);
        }
        return $invoiceId;
    }

    /**
     * Calculate a plan change. Upgrades are charged pro-rata for the remainder of the
     * current period; downgrades take effect immediately with no credit and the new
     * price applies from the next renewal.
     */
    public static function quotePlanChange(array $service, array $newPlan): array
    {
        $newPrice = BillingCycle::planPrice($newPlan, $service['billing_cycle']);
        if ($newPrice === null) {
            throw new \InvalidArgumentException('The selected plan is not offered with the service\'s ' . cycle_label($service['billing_cycle']) . ' billing cycle.');
        }
        $charge = 0.0;
        if ($service['next_due_date'] && $service['status'] === 'active') {
            $periodEnd = strtotime($service['next_due_date']);
            $periodStart = strtotime(BillingCycle::addPeriod($service['next_due_date'], $service['billing_cycle'], -1));
            $total = max(1, ($periodEnd - $periodStart) / 86400);
            $remaining = max(0, ($periodEnd - strtotime(today())) / 86400);
            $charge = round(($newPrice - (float) $service['price']) * ($remaining / $total), 2);
        }
        return ['new_price' => $newPrice, 'charge' => max(0.0, $charge), 'is_upgrade' => $newPrice > (float) $service['price']];
    }

    /**
     * Request a plan change. Upgrades with a charge create an invoice (applied on payment);
     * otherwise the change is applied immediately through the provider.
     * Returns ['invoice_id' => ?int, 'result' => ?ProviderResult-like array].
     */
    public static function requestPlanChange(int $serviceId, int $newPlanId): array
    {
        $service = db()->fetch('SELECT * FROM services WHERE id = ?', [$serviceId]);
        $newPlan = db()->fetch("SELECT * FROM hosting_plans WHERE id = ? AND status = 'active'", [$newPlanId]);
        if (!$service || !$newPlan) {
            throw new \InvalidArgumentException('Service or plan not found.');
        }
        if ($service['status'] !== 'active') {
            throw new \InvalidArgumentException('Only active services can change plan.');
        }
        if ((int) $newPlan['id'] === (int) $service['plan_id']) {
            throw new \InvalidArgumentException('The service is already on this plan.');
        }
        if ($newPlan['type'] !== $service['type'] || (int) $newPlan['provider_id'] !== (int) $service['provider_id']) {
            throw new \InvalidArgumentException('The selected plan is not compatible with this service.');
        }
        $open = db()->fetchColumn(
            "SELECT i.number FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE ii.service_id = ? AND ii.item_type = 'upgrade' AND i.status IN ('pending','overdue','draft') LIMIT 1",
            [$serviceId]
        );
        if ($open) {
            throw new \InvalidArgumentException("There is already an unpaid plan-change invoice ({$open}) for this service.");
        }
        $quote = self::quotePlanChange($service, $newPlan);
        if ($quote['charge'] > 0) {
            $invoiceId = InvoiceService::create((int) $service['customer_id'], [[
                'service_id' => $serviceId,
                'item_type' => 'upgrade',
                'description' => sprintf('Plan upgrade to %s — %s (pro-rata until %s)', $newPlan['name'], $service['domain'], date_fmt($service['next_due_date'])),
                'unit_price' => $quote['charge'],
                'meta' => ['new_plan_id' => (int) $newPlan['id'], 'new_price' => $quote['new_price'], 'old_plan_id' => (int) $service['plan_id']],
            ]], ['due_date' => today()]);
            InvoiceService::notifyCreated($invoiceId);
            return ['invoice_id' => $invoiceId, 'result' => null];
        }
        $result = ProvisioningService::changePlan($serviceId, (int) $newPlan['id'], $quote['new_price']);
        return ['invoice_id' => null, 'result' => $result];
    }

    /** After a price change, update an unpaid renewal invoice for the service so it reflects the new price. */
    public static function repriceOpenRenewal(int $serviceId): void
    {
        $s = db()->fetch('SELECT * FROM services WHERE id = ?', [$serviceId]);
        $item = db()->fetch(
            "SELECT ii.* FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
             WHERE ii.service_id = ? AND ii.item_type = 'renewal' AND i.status IN ('pending','draft') AND i.amount_paid = 0 ORDER BY ii.id DESC LIMIT 1",
            [$serviceId]
        );
        if ($s && $item && (float) $item['unit_price'] !== (float) $s['price']) {
            db()->update('invoice_items', ['unit_price' => $s['price'], 'amount' => round((float) $s['price'] * (float) $item['quantity'], 2)], 'id = :id', ['id' => $item['id']]);
            InvoiceService::recalculate((int) $item['invoice_id']);
        }
    }

    /**
     * Apply the local (database) effects of a fully paid invoice. Runs inside the payment
     * transaction. Idempotent through invoice_items.applied_at.
     * Returns a list of follow-up actions that require external API calls.
     */
    public static function applyPaidInvoice(int $invoiceId): array
    {
        $db = db();
        $followUps = [];
        $items = $db->fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? AND (service_id IS NOT NULL OR domain_id IS NOT NULL) AND applied_at IS NULL FOR UPDATE', [$invoiceId]);
        foreach ($items as $item) {
            if ($item['domain_id'] !== null) {
                DomainService::applyPaidItem($db, $item);
                continue;
            }
            $service = $db->fetch('SELECT * FROM services WHERE id = ? FOR UPDATE', [$item['service_id']]);
            if (!$service) {
                continue;
            }
            $markApplied = true;
            switch ($item['item_type']) {
                case 'new_service':
                    if ($service['status'] === 'pending_payment' || $service['status'] === 'cancelled') {
                        $db->update('services', [
                            'status' => 'provisioning',
                            'activation_date' => today(),
                            'next_due_date' => BillingCycle::addPeriod(today(), $service['billing_cycle']),
                            'updated_at' => now(),
                        ], 'id = :id', ['id' => $service['id']]);
                        $followUps[] = ['action' => 'provision', 'service_id' => (int) $service['id']];
                    }
                    break;
                case 'renewal':
                    if ($service['next_due_date'] === $item['period_start'] && $item['period_end']) {
                        $db->update('services', ['next_due_date' => $item['period_end'], 'termination_flagged_at' => null, 'updated_at' => now()], 'id = :id', ['id' => $service['id']]);
                    } else {
                        AutomationLog::record('billing', 'renewal_date_not_advanced', 'info', "Invoice item {$item['id']} period {$item['period_start']} does not match service next due date {$service['next_due_date']}; due date left unchanged for staff review.", 'service', (int) $service['id']);
                    }
                    if ($service['status'] === 'suspended' && $service['suspension_type'] === 'billing') {
                        $followUps[] = ['action' => 'unsuspend', 'service_id' => (int) $service['id']];
                    }
                    break;
                case 'upgrade':
                    $meta = json_decode((string) $item['meta'], true) ?: [];
                    if (!empty($meta['new_plan_id'])) {
                        $followUps[] = ['action' => 'change_plan', 'service_id' => (int) $service['id'], 'item_id' => (int) $item['id'], 'plan_id' => (int) $meta['new_plan_id'], 'price' => (float) ($meta['new_price'] ?? 0)];
                        $markApplied = false; // applied after the provider confirms the change
                    }
                    break;
            }
            if ($markApplied) {
                $db->update('invoice_items', ['applied_at' => now()], 'id = :id', ['id' => $item['id']]);
            }
        }
        return $followUps;
    }

    /**
     * Queue external follow-up actions for the automation runner instead of
     * executing them inside the payment request. WHM calls can take tens of
     * seconds and would hang the customer's payment return page and gateway
     * webhooks. The cron tasks pick the work up from database state:
     *   provision     → retry_provisioning      (service status 'provisioning')
     *   unsuspend     → reactivate_paid         (billing suspension, no overdue invoices)
     *   change_plan   → apply_pending_changes   (paid upgrade item, applied_at NULL)
     */
    public static function runFollowUps(array $followUps): void
    {
        if (!$followUps) {
            return;
        }
        $summary = implode(', ', array_map(
            fn ($f) => $f['action'] . ' service #' . $f['service_id'],
            $followUps
        ));
        AutomationLog::record('billing', 'followups_queued', 'info', "Queued for automation: {$summary}", 'service', (int) $followUps[0]['service_id']);
    }

    /** True when the service still has another unpaid renewal invoice past the grace period. */
    public static function hasBlockingOverdue(int $serviceId): bool
    {
        $cutoff = date('Y-m-d', strtotime('-' . Settings::int('suspend_grace_days') . ' days'));
        return (bool) db()->fetchColumn(
            "SELECT COUNT(*) FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
             WHERE ii.service_id = ? AND ii.item_type = 'renewal' AND i.status IN ('pending','overdue') AND i.due_date <= ?",
            [$serviceId, $cutoff]
        );
    }

    /** Notify the customer about a received payment. */
    public static function notifyPayment(int $paymentId): void
    {
        $p = db()->fetch('SELECT p.*, i.number, i.id AS inv_id FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.id = ?', [$paymentId]);
        if ($p) {
            NotificationService::notifyCustomer((int) $p['customer_id'], 'payment_received', [
                'invoice_number' => $p['number'],
                'amount' => money($p['amount'], $p['currency']),
                'method' => PaymentService::methodLabel($p['gateway'], $p['method']),
                'reference' => $p['gateway_reference'] ?: $p['reference'],
                'invoice_url' => absolute_url('/client/invoices/' . $p['inv_id']),
            ], 'payment_received:' . $p['id']);
        }
    }
}
