<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Services\BillingService;

final class OrderController extends Controller
{
    public function index(): string
    {
        return $this->view('client.order.index', [
            'title' => 'Order hosting',
            'plans' => db()->fetchAll("SELECT * FROM hosting_plans WHERE status = 'active' ORDER BY type, sort_order, name"),
        ]);
    }

    /** Create a service order (service + invoice), then send the client to pay. */
    public function store(): never
    {
        $data = $this->validate([
            'plan_id' => 'required|integer|exists:hosting_plans,id',
            'domain' => 'required|domain',
            'billing_cycle' => 'required|in:monthly,quarterly,semiannual,annual',
        ], ['plan_id' => 'Hosting plan']);
        try {
            $r = BillingService::createOrder(Auth::customerId(), (int) $data['plan_id'], $data['domain'], $data['billing_cycle']);
        } catch (\InvalidArgumentException $e) {
            back_with_errors(['domain' => $e->getMessage()], '/client/order');
        }
        audit('client.order_created', 'service', $r['service_id'], ['domain' => $data['domain']]);
        flash('success', 'Order created. Pay the invoice to activate your service.');
        redirect('/client/invoices/' . $r['invoice_id']);
    }
}
