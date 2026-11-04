<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Services\Notifications\NotificationService;

/**
 * Invoice creation and lifecycle. Invoices are only marked paid by
 * PaymentService after a verified/authorised payment.
 */
final class InvoiceService
{
    /**
     * Create an invoice with items. Each item: description, unit_price, quantity?, item_type?, service_id?,
     * period_start?, period_end?, meta? (array).
     * Must be used inside or outside a transaction (it joins an existing one).
     */
    public static function create(int $customerId, array $items, array $options = []): int
    {
        if (!$items) {
            throw new \InvalidArgumentException('An invoice needs at least one item.');
        }
        return db()->transaction(function ($db) use ($customerId, $items, $options) {
            $issue = $options['issue_date'] ?? today();
            $due = $options['due_date'] ?? date('Y-m-d', strtotime($issue . ' +' . Settings::int('invoice_due_days') . ' days'));
            $status = ($options['draft'] ?? false) ? 'draft' : 'pending';
            $invoiceId = $db->insert('invoices', [
                'number' => Settings::nextInvoiceNumber(),
                'customer_id' => $customerId,
                'status' => $status,
                'currency' => (string) Settings::get('currency', 'USD'),
                'subtotal' => 0,
                'discount' => round((float) ($options['discount'] ?? 0), 2),
                'total' => 0,
                'amount_paid' => 0,
                'issue_date' => $issue,
                'due_date' => $due,
                'notes' => $options['notes'] ?? null,
                'created_by' => $options['created_by'] ?? Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $unit = round((float) $item['unit_price'], 2);
                $db->insert('invoice_items', [
                    'invoice_id' => $invoiceId,
                    'service_id' => $item['service_id'] ?? null,
                    'domain_id' => $item['domain_id'] ?? null,
                    'item_type' => $item['item_type'] ?? 'custom',
                    'description' => mb_substr((string) $item['description'], 0, 255),
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'amount' => round($qty * $unit, 2),
                    'period_start' => $item['period_start'] ?? null,
                    'period_end' => $item['period_end'] ?? null,
                    'meta' => isset($item['meta']) ? json_encode($item['meta']) : null,
                ]);
            }
            self::recalculate($invoiceId);
            if ((float) $db->fetchColumn('SELECT total FROM invoices WHERE id = ?', [$invoiceId]) < 0) {
                throw new \InvalidArgumentException('Invoice total cannot be negative.');
            }
            return $invoiceId;
        });
    }

    public static function recalculate(int $invoiceId): void
    {
        $subtotal = (float) db()->fetchColumn('SELECT COALESCE(SUM(amount), 0) FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);
        $discount = (float) db()->fetchColumn('SELECT discount FROM invoices WHERE id = ?', [$invoiceId]);
        $discount = min($discount, $subtotal);
        db()->update('invoices', [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => round($subtotal - $discount, 2),
            'updated_at' => now(),
        ], 'id = :id', ['id' => $invoiceId]);
    }

    public static function find(int $id): ?array
    {
        return db()->fetch('SELECT i.*, c.full_name, c.business_name, c.email, c.phone, c.address, c.city, c.country FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = ?', [$id]);
    }

    public static function items(int $invoiceId): array
    {
        return db()->fetchAll('SELECT ii.*, s.domain, d.domain AS domain_name FROM invoice_items ii LEFT JOIN services s ON s.id = ii.service_id LEFT JOIN domains d ON d.id = ii.domain_id WHERE ii.invoice_id = ? ORDER BY ii.id', [$invoiceId]);
    }

    public static function balance(array $invoice): float
    {
        return max(0.0, round((float) $invoice['total'] - (float) $invoice['amount_paid'], 2));
    }

    public static function isPayable(array $invoice): bool
    {
        return in_array($invoice['status'], ['pending', 'overdue'], true) && self::balance($invoice) > 0;
    }

    /** Publish a draft invoice (draft → pending) and notify the customer. */
    public static function publish(int $invoiceId): void
    {
        $changed = db()->update('invoices', ['status' => 'pending', 'issue_date' => today(), 'updated_at' => now()], "id = :id AND status = 'draft'", ['id' => $invoiceId]);
        if ($changed) {
            self::notifyCreated($invoiceId);
        }
    }

    public static function notifyCreated(int $invoiceId): void
    {
        $inv = self::find($invoiceId);
        if (!$inv || $inv['status'] === 'draft') {
            return;
        }
        NotificationService::notifyCustomer((int) $inv['customer_id'], 'invoice_created', [
            'invoice_number' => $inv['number'],
            'total' => money($inv['total'], $inv['currency']),
            'due_date' => date_fmt($inv['due_date']),
            'invoice_url' => absolute_url('/client/invoices/' . $inv['id']),
        ], 'invoice_created:' . $inv['id']);
    }

    /**
     * Cancel an unpaid invoice. Services that were waiting on this invoice as a
     * new order are cancelled too. Paid invoices cannot be cancelled.
     */
    public static function cancel(int $invoiceId, string $reason = ''): void
    {
        db()->transaction(function ($db) use ($invoiceId, $reason) {
            $inv = $db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv) {
                throw new \RuntimeException('Invoice not found.');
            }
            if (!in_array($inv['status'], ['draft', 'pending', 'overdue'], true)) {
                throw new \RuntimeException('Only unpaid invoices can be cancelled.');
            }
            if ((float) $inv['amount_paid'] > 0) {
                throw new \RuntimeException('This invoice has partial payments recorded and cannot be cancelled.');
            }
            $db->update('invoices', [
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'notes' => trim(((string) $inv['notes']) . ($reason !== '' ? "\nCancelled: {$reason}" : '')),
                'updated_at' => now(),
            ], 'id = :id', ['id' => $invoiceId]);
            foreach ($db->fetchAll("SELECT service_id FROM invoice_items WHERE invoice_id = ? AND item_type = 'new_service' AND service_id IS NOT NULL", [$invoiceId]) as $row) {
                $db->update('services', ['status' => 'cancelled', 'updated_at' => now()], "id = :id AND status = 'pending_payment'", ['id' => $row['service_id']]);
            }
            foreach ($db->fetchAll("SELECT domain_id FROM invoice_items WHERE invoice_id = ? AND item_type = 'domain' AND domain_id IS NOT NULL", [$invoiceId]) as $row) {
                $db->update('domains', ['status' => 'cancelled', 'updated_at' => now()], "id = :id AND status = 'pending_payment'", ['id' => $row['domain_id']]);
            }
        });
    }

    /** Mark pending invoices past their due date as overdue. Returns count. */
    public static function markOverdue(): int
    {
        return db()->query("UPDATE invoices SET status = 'overdue', updated_at = NOW() WHERE status = 'pending' AND due_date < CURDATE()")->rowCount();
    }

    public static function customerBalance(int $customerId): float
    {
        return (float) db()->fetchColumn("SELECT COALESCE(SUM(total - amount_paid), 0) FROM invoices WHERE customer_id = ? AND status IN ('pending','overdue')", [$customerId]);
    }
}
