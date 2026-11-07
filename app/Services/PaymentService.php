<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Services\Payments\GatewayRegistry;
use App\Services\Payments\GatewayStatus;

/**
 * The single place where payments are applied to invoices.
 * Gateways report verified results here; they never mark invoices paid directly.
 */
final class PaymentService
{
    public const MANUAL_METHODS = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money (manual)', 'other' => 'Other'];

    public static function methodLabel(string $gateway, ?string $method): string
    {
        if ($gateway === 'manual') {
            return self::MANUAL_METHODS[$method] ?? ucfirst((string) $method);
        }
        $gw = GatewayRegistry::get($gateway);
        return ($gw ? $gw->name() : ucfirst($gateway)) . ($method ? ' (' . ucfirst($method) . ')' : '');
    }

    public static function newReference(): string
    {
        do {
            $ref = 'PAY-' . strtoupper(bin2hex(random_bytes(5)));
        } while (db()->fetchColumn('SELECT id FROM payments WHERE reference = ?', [$ref]));
        return $ref;
    }

    /**
     * Record an authorised manual payment (cash / bank transfer ...). Returns payment id.
     */
    public static function recordManual(int $invoiceId, float $amount, string $method, string $reference, string $date, ?string $note = null, ?int $staffId = null): int
    {
        if (!isset(self::MANUAL_METHODS[$method])) {
            throw new \InvalidArgumentException('Invalid payment method.');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
        if (trim($reference) === '') {
            throw new \InvalidArgumentException('A payment reference is required.');
        }
        if ($date > today()) {
            throw new \InvalidArgumentException('Payment date cannot be in the future.');
        }
        $followUps = [];
        $paymentId = db()->transaction(function ($db) use ($invoiceId, $amount, $method, $reference, $date, $note, $staffId, &$followUps) {
            $inv = $db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv || !InvoiceService::isPayable($inv)) {
                throw new \InvalidArgumentException('This invoice is not open for payment.');
            }
            $balance = InvoiceService::balance($inv);
            if (round($amount, 2) > $balance) {
                throw new \InvalidArgumentException('Amount exceeds the invoice balance of ' . money($balance) . '.');
            }
            $dup = $db->fetchColumn("SELECT id FROM payments WHERE gateway = 'manual' AND gateway_reference = ?", [trim($reference)]);
            if ($dup) {
                throw new \InvalidArgumentException('A manual payment with this reference has already been recorded.');
            }
            $id = $db->insert('payments', [
                'reference' => self::newReference(),
                'invoice_id' => $invoiceId,
                'customer_id' => $inv['customer_id'],
                'gateway' => 'manual',
                'method' => $method,
                'amount' => round($amount, 2),
                'currency' => $inv['currency'],
                'status' => 'completed',
                'gateway_reference' => trim($reference),
                'paid_at' => $date . ' ' . date('H:i:s'),
                'recorded_by' => $staffId ?? Auth::id(),
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $followUps = self::applyToInvoice($id);
            return $id;
        });
        audit('payment.manual_recorded', 'invoice', $invoiceId, ['payment_id' => $paymentId, 'amount' => $amount, 'method' => $method, 'reference' => $reference, 'date' => $date, 'note' => $note]);
        BillingService::notifyPayment($paymentId);
        BillingService::runFollowUps($followUps);
        return $paymentId;
    }

    /** Create a pending gateway payment for an invoice's outstanding balance. */
    public static function createPending(int $invoiceId, string $gateway, ?string $method = null): array
    {
        return db()->transaction(function ($db) use ($invoiceId, $gateway, $method) {
            $inv = $db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv || !InvoiceService::isPayable($inv)) {
                throw new \InvalidArgumentException('This invoice is not open for payment.');
            }
            $id = $db->insert('payments', [
                'reference' => self::newReference(),
                'invoice_id' => $invoiceId,
                'customer_id' => $inv['customer_id'],
                'gateway' => $gateway,
                'method' => $method,
                'amount' => InvoiceService::balance($inv),
                'currency' => $inv['currency'],
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $db->fetch('SELECT * FROM payments WHERE id = ?', [$id]);
        });
    }

    /**
     * Apply a verified gateway status to a pending payment. Idempotent and safe to
     * call repeatedly (webhook, browser return, polling, cron reconciliation).
     * Returns the updated payment row.
     */
    public static function applyGatewayStatus(int $paymentId, GatewayStatus $status): array
    {
        $followUps = [];
        $completedNow = false;
        db()->transaction(function ($db) use ($paymentId, $status, &$followUps, &$completedNow) {
            $p = $db->fetch('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if (!$p) {
                throw new \RuntimeException('Payment not found.');
            }
            $update = ['gateway_status' => $status->gatewayStatus, 'last_checked_at' => now(), 'updated_at' => now(), 'raw_response' => mb_substr($status->raw, 0, 5000)];
            if ($status->gatewayReference && !$p['gateway_reference']) {
                $update['gateway_reference'] = $status->gatewayReference;
            }
            if ($p['status'] === 'completed') {
                $db->update('payments', ['last_checked_at' => now()], 'id = :id', ['id' => $paymentId]);
                return; // already processed — never apply twice
            }
            if ($status->state === GatewayStatus::PAID) {
                if ($status->amount !== null && round($status->amount, 2) < round((float) $p['amount'], 2)) {
                    $update['note'] = 'Gateway reported amount ' . number_format($status->amount, 2) . ' lower than expected ' . $p['amount'] . '. Not applied — review required.';
                    $db->update('payments', $update, 'id = :id', ['id' => $paymentId]);
                    AutomationLog::record('payments', 'amount_mismatch', 'failed', $update['note'], 'payment', $paymentId);
                    return;
                }
                $update['status'] = 'completed';
                $update['paid_at'] = now();
                $db->update('payments', $update, 'id = :id', ['id' => $paymentId]);
                $followUps = self::applyToInvoice($paymentId);
                $completedNow = true;
            } elseif (in_array($status->state, [GatewayStatus::FAILED, GatewayStatus::CANCELLED], true)) {
                $update['status'] = $status->state === GatewayStatus::FAILED ? 'failed' : 'cancelled';
                $db->update('payments', $update, 'id = :id', ['id' => $paymentId]);
            } else {
                $db->update('payments', $update, 'id = :id', ['id' => $paymentId]);
            }
        });
        if ($completedNow) {
            audit('payment.gateway_completed', 'payment', $paymentId, ['gateway_status' => $status->gatewayStatus]);
            BillingService::notifyPayment($paymentId);
            BillingService::runFollowUps($followUps);
        }
        return db()->fetch('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    }

    /**
     * Add a completed payment to its invoice (inside a transaction). When the invoice
     * becomes fully paid its local effects are applied. Returns follow-up actions.
     */
    private static function applyToInvoice(int $paymentId): array
    {
        $db = db();
        $p = $db->fetch('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        $inv = $db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$p['invoice_id']]);
        if (!in_array($inv['status'], ['pending', 'overdue'], true)) {
            // Money was received for an invoice that is no longer open (e.g. paid manually meanwhile).
            $db->update('payments', ['note' => trim(($p['note'] ?? '') . ' Invoice was already ' . $inv['status'] . ' — credit or refund required.')], 'id = :id', ['id' => $paymentId]);
            AutomationLog::record('payments', 'payment_on_closed_invoice', 'failed', "Payment {$p['reference']} received for invoice {$inv['number']} which is {$inv['status']}. Review required.", 'payment', $paymentId);
            return [];
        }
        $paid = round((float) $inv['amount_paid'] + (float) $p['amount'], 2);
        $update = ['amount_paid' => $paid, 'updated_at' => now()];
        $followUps = [];
        if ($paid >= round((float) $inv['total'], 2)) {
            $update += ['status' => 'paid', 'paid_at' => now(), 'payment_reference' => $p['gateway_reference'] ?: $p['reference']];
            $db->update('invoices', $update, 'id = :id', ['id' => $inv['id']]);
            $followUps = BillingService::applyPaidInvoice((int) $inv['id']);
        } else {
            $db->update('invoices', $update, 'id = :id', ['id' => $inv['id']]);
        }
        return $followUps;
    }

    /** Verify a pending gateway payment with the provider (polling) and apply the result. */
    public static function verify(int $paymentId): array
    {
        $p = db()->fetch('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        if (!$p) {
            throw new \RuntimeException('Payment not found.');
        }
        if ($p['status'] !== 'pending') {
            return $p;
        }
        $gateway = GatewayRegistry::get($p['gateway']);
        if (!$gateway) {
            throw new \RuntimeException("Payment gateway '{$p['gateway']}' is not available.");
        }
        return self::applyGatewayStatus($paymentId, $gateway->verify($p));
    }
}
