<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Services\InvoiceService;
use App\Services\Payments\GatewayRegistry;
use App\Services\Payments\PaynowGateway;
use App\Services\PaymentService;
use App\Services\PdfService;

final class InvoiceController extends Controller
{
    public function index(): string
    {
        $status = (string) Request::query('status', '');
        $where = ['customer_id = ?', "status <> 'draft'"];
        $params = [Auth::customerId()];
        if (in_array($status, ['pending', 'overdue', 'paid', 'cancelled'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $invoices = db()->fetchAll('SELECT * FROM invoices WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200', $params);
        $stats = db()->fetch(
            "SELECT COALESCE(SUM(CASE WHEN status IN ('pending','overdue') THEN total - amount_paid END),0) AS balance,
                    SUM(status = 'overdue') AS overdue_count,
                    COALESCE(SUM(CASE WHEN status = 'paid' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN total END),0) AS paid_month
             FROM invoices WHERE customer_id = ? AND status <> 'draft'",
            [Auth::customerId()]
        );
        return $this->view('client.invoices.index', [
            'title' => 'My invoices',
            'invoices' => $invoices,
            'stats' => $stats,
            'status' => $status,
        ]);
    }

    public function show(int $id): string
    {
        $invoice = $this->invoice($id);
        $paynow = GatewayRegistry::get('paynow');
        $stripe = GatewayRegistry::get('stripe');
        return $this->view('client.invoices.show', [
            'title' => 'Invoice ' . $invoice['number'],
            'invoice' => $invoice,
            'items' => InvoiceService::items($id),
            'balance' => InvoiceService::balance($invoice),
            'payable' => InvoiceService::isPayable($invoice),
            'payments' => db()->fetchAll("SELECT * FROM payments WHERE invoice_id = ? AND status <> 'pending' ORDER BY id DESC", [$id]),
            'paynow' => $paynow && $paynow->isAvailable() ? $paynow : null,
            'stripe' => $stripe && $stripe->isAvailable() ? $stripe : null,
            'mobile' => $paynow instanceof PaynowGateway && $paynow->mobileEnabled(),
            'bankInstructions' => (string) setting('bank_instructions', ''),
        ]);
    }

    public function pdf(int $id): never
    {
        $this->invoice($id);
        PdfService::sendInvoice($id);
    }

    /**
     * Start a gateway payment for the invoice balance.
     * method=web (default) redirects to Paynow; method=mobile sends a prompt to the phone.
     */
    public function pay(int $id): never
    {
        $invoice = $this->invoice($id);
        if (!InvoiceService::isPayable($invoice)) {
            flash('error', 'This invoice is not open for payment.');
            redirect('/client/invoices/' . $id);
        }
        $method = (string) Request::input('method', 'web');
        $phone = preg_replace('/\D+/', '', (string) Request::input('phone', ''));
        $gatewayId = (string) Request::input('gateway', 'paynow');
        $mobile = $gatewayId === 'paynow' && $method === 'mobile' && $phone !== '';
        try {
            $gateway = GatewayRegistry::get($gatewayId);
            if (!$gateway || !$gateway->isAvailable()) {
                throw new \RuntimeException('This payment method is not configured. Please contact us to pay this invoice.');
            }
            $payment = PaymentService::createPending($id, $gatewayId, $mobile ? (string) Request::input('mobile_method', 'ecocash') : null);
            $result = $gateway->initiate($payment, $invoice, $this->customer(), $mobile ? ['phone' => $phone, 'method' => (string) Request::input('mobile_method', 'ecocash')] : []);
            db()->update('payments', [
                'poll_url' => $result['poll_url'] ?? null,
                'gateway_reference' => $result['gateway_reference'] ?? null,
                'updated_at' => now(),
            ], 'id = :id', ['id' => $payment['id']]);
            audit('client.payment_initiated', 'invoice', $id, ['reference' => $payment['reference']]);
            if (!empty($result['redirect'])) {
                header('Location: ' . $result['redirect'], true, 302);
                exit;
            }
            flash('info', $result['instructions'] ?? 'Check your phone to approve the payment.');
            redirect('/client/payments/' . $payment['reference'] . '/return');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/client/invoices/' . $id);
        }
    }

    private function invoice(int $id): array
    {
        $invoice = db()->fetch("SELECT * FROM invoices WHERE id = ? AND customer_id = ? AND status <> 'draft'", [$id, Auth::customerId()]);
        if (!$invoice) {
            abort(404);
        }
        return $invoice;
    }

    private function customer(): array
    {
        return db()->fetch('SELECT * FROM customers WHERE id = ?', [Auth::customerId()]) ?: [];
    }
}
