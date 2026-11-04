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
        $invoices = db()->fetchAll("SELECT * FROM invoices WHERE customer_id = ? AND status <> 'draft' ORDER BY id DESC LIMIT 200", [Auth::customerId()]);
        return $this->view('client.invoices.index', [
            'title' => 'My invoices',
            'invoices' => $invoices,
            'balance' => InvoiceService::customerBalance(Auth::customerId()),
        ]);
    }

    public function show(int $id): string
    {
        $invoice = $this->invoice($id);
        $paynow = GatewayRegistry::get('paynow');
        return $this->view('client.invoices.show', [
            'title' => 'Invoice ' . $invoice['number'],
            'invoice' => $invoice,
            'items' => InvoiceService::items($id),
            'balance' => InvoiceService::balance($invoice),
            'payable' => InvoiceService::isPayable($invoice),
            'payments' => db()->fetchAll("SELECT * FROM payments WHERE invoice_id = ? AND status <> 'pending' ORDER BY id DESC", [$id]),
            'paynow' => $paynow && $paynow->isAvailable() ? $paynow : null,
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
        $mobile = $method === 'mobile' && $phone !== '';
        try {
            $payment = PaymentService::createPending($id, 'paynow', $mobile ? (string) Request::input('mobile_method', 'ecocash') : null);
            $gateway = GatewayRegistry::get('paynow');
            if (!$gateway || !$gateway->isAvailable()) {
                throw new \RuntimeException('Online payments are not configured. Please contact us to pay this invoice.');
            }
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
