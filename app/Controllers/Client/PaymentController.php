<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Services\PaymentService;

final class PaymentController extends Controller
{
    public function index(): string
    {
        $payments = db()->fetchAll(
            "SELECT p.*, i.number FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.customer_id = ? ORDER BY p.id DESC LIMIT 200",
            [Auth::customerId()]
        );
        return $this->view('client.payments.index', ['title' => 'Payments', 'payments' => $payments]);
    }

    /**
     * Paynow return URL. The customer lands here after paying (or cancelling).
     * We confirm status by polling — the poll page keeps checking in the background.
     */
    public function returned(string $reference): string
    {
        $payment = $this->owned($reference);
        try {
            $payment = PaymentService::verify((int) $payment['id']);
        } catch (\Throwable) {
            // polling continues in the background
        }
        return $this->view('client.payments.return', [
            'title' => 'Payment status',
            'payment' => $payment,
            'invoice' => db()->fetch('SELECT * FROM invoices WHERE id = ?', [$payment['invoice_id']]),
        ]);
    }

    /** JSON status for the poll page. */
    public function status(string $reference): never
    {
        $payment = $this->owned($reference);
        try {
            $payment = PaymentService::verify((int) $payment['id']);
        } catch (\Throwable) {
            // keep polling
        }
        json_response([
            'status' => $payment['status'],
            'message' => match ($payment['status']) {
                'completed' => 'Payment received. Redirecting…',
                'failed' => 'Payment failed. Redirecting…',
                'cancelled' => 'Payment cancelled. Redirecting…',
                default => 'Waiting for payment confirmation…',
            },
        ]);
    }

    private function owned(string $reference): array
    {
        $payment = db()->fetch('SELECT * FROM payments WHERE reference = ? AND customer_id = ?', [$reference, Auth::customerId()]);
        if (!$payment) {
            abort(404);
        }
        return $payment;
    }
}
