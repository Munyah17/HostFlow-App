<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Services\BillingService;
use App\Services\ProvisioningService;

final class ServiceController extends Controller
{
    public function web(): string
    {
        return $this->listing('web', 'My services');
    }

    public function email(): string
    {
        return $this->listing('email', 'Email hosting');
    }

    private function listing(string $type, string $title): string
    {
        $services = db()->fetchAll(
            "SELECT s.*, p.name AS plan_name FROM services s JOIN hosting_plans p ON p.id = s.plan_id
             WHERE s.customer_id = ? AND s.type = ? AND s.status NOT IN ('terminated','cancelled') ORDER BY s.domain",
            [Auth::customerId(), $type]
        );
        return $this->view('client.services.index', compact('services') + ['title' => $title, 'type' => $type]);
    }

    public function show(int $id): string
    {
        $s = $this->service($id);
        return $this->view('client.services.show', [
            'title' => $s['domain'],
            's' => $s,
            'altPlans' => db()->fetchAll("SELECT id, name, price_monthly, price_quarterly, price_semiannual, price_annual FROM hosting_plans WHERE type = ? AND status = 'active' AND provider_id <=> ? AND id <> ? ORDER BY sort_order, name", [$s['type'], $s['provider_id'], $s['plan_id']]),
            'invoices' => db()->fetchAll("SELECT DISTINCT i.* FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id WHERE ii.service_id = ? AND i.status <> 'draft' ORDER BY i.id DESC LIMIT 10", [$id]),
        ]);
    }

    /** Generate the renewal invoice and take the client to pay it. */
    public function renew(int $id): never
    {
        $this->service($id);
        $invoiceId = BillingService::generateRenewal($id, force: true);
        if (!$invoiceId) {
            flash('error', 'This service is not due for renewal yet.');
            redirect('/client/services/' . $id);
        }
        flash('success', 'Your renewal invoice is ready.');
        redirect('/client/invoices/' . $invoiceId);
    }

    public function changePlan(int $id): never
    {
        $this->service($id);
        try {
            $r = BillingService::requestPlanChange($id, (int) Request::input('plan_id', 0));
            if ($r['invoice_id']) {
                flash('success', 'An invoice for the upgrade was created. Your plan changes once it is paid.');
                redirect('/client/invoices/' . $r['invoice_id']);
            }
            flash($r['result']['success'] ? 'success' : 'error', $r['result']['message']);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/client/services/' . $id);
    }

    /** Single sign-on into cPanel/webmail. */
    public function login(int $id): never
    {
        $s = $this->service($id);
        $app = $s['type'] === 'email' ? 'webmail' : (Request::input('app') ?: null);
        $r = ProvisioningService::loginUrl($id, 'cpanel', $app !== null && $app !== '' ? (string) $app : null);
        if (!$r['success']) {
            flash('error', $r['message']);
            redirect('/client/services/' . $id);
        }
        header('Location: ' . $r['url'], true, 302);
        exit;
    }

    private function service(int $id): array
    {
        $s = db()->fetch(
            'SELECT s.*, p.name AS plan_name, hp.name AS provider_name FROM services s JOIN hosting_plans p ON p.id = s.plan_id LEFT JOIN hosting_providers hp ON hp.id = s.provider_id WHERE s.id = ? AND s.customer_id = ?',
            [$id, Auth::customerId()]
        );
        if (!$s) {
            abort(404);
        }
        return $s;
    }
}
