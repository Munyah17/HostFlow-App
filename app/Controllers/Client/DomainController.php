<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Services\DomainService;
use App\Services\Settings;

final class DomainController extends Controller
{
    public function index(): string
    {
        return $this->view('client.domains.index', [
            'title' => 'My domains',
            'domains' => db()->fetchAll('SELECT * FROM domains WHERE customer_id = ? ORDER BY expiry_date IS NULL, expiry_date', [Auth::customerId()]),
            'ordering' => Settings::bool('domain_orders_enabled'),
        ]);
    }

    public function show(int $id): string
    {
        $d = $this->findOwnedOrFail('domains', $id);
        return $this->view('client.domains.show', [
            'title' => $d['domain'],
            'd' => $d,
            'invoices' => db()->fetchAll(
                'SELECT DISTINCT i.* FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id WHERE ii.domain_id = ? ORDER BY i.id DESC LIMIT 20',
                [$id]
            ),
        ]);
    }

    /** Domain order form. */
    public function order(): string
    {
        if (!Settings::bool('domain_orders_enabled')) {
            flash('error', 'Domain orders are not open right now — contact us to register a domain.');
            redirect('/client/domains');
        }
        return $this->view('client.domains.order', [
            'title' => 'Register a domain',
            'pricing' => db()->fetchAll("SELECT * FROM domain_pricing WHERE status = 'active' ORDER BY sort_order, tld"),
        ]);
    }

    /** Create the domain order (domain + invoice), then send the client to pay. */
    public function store(): never
    {
        if (!Settings::bool('domain_orders_enabled')) {
            redirect('/client/domains');
        }
        $data = $this->validate([
            'domain' => 'required|domain',
            'years' => 'integer|min:1|max:10',
        ]);
        try {
            $r = DomainService::order(Auth::customerId(), $data['domain'], (int) ($data['years'] ?: 1), ['auto_renew' => true]);
        } catch (\InvalidArgumentException $e) {
            back_with_errors(['domain' => $e->getMessage()], '/client/domains/order');
        }
        audit('client.domain_ordered', 'domain', $r['domain_id'], ['domain' => $data['domain']]);
        flash('success', 'Domain order created. Pay the invoice to register it.');
        redirect('/client/invoices/' . $r['invoice_id']);
    }

    /** Generate a renewal invoice and send the client to pay it. */
    public function renew(int $id): never
    {
        $d = $this->findOwnedOrFail('domains', $id);
        $invoiceId = DomainService::generateRenewal((int) $d['id'], force: true);
        if ($invoiceId) {
            audit('client.domain_renewal_requested', 'domain', (int) $d['id'], ['invoice_id' => $invoiceId]);
            flash('success', 'Your renewal invoice is ready.');
            redirect('/client/invoices/' . $invoiceId);
        }
        flash('error', 'A renewal invoice could not be generated — contact support.');
        redirect('/client/domains/' . $d['id']);
    }
}
