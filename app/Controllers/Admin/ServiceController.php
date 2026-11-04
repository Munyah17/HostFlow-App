<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Services\BillingCycle;
use App\Services\BillingService;
use App\Services\ProvisioningService;

final class ServiceController extends Controller
{
    private const STATUSES = ['pending_payment', 'provisioning', 'active', 'suspended', 'provisioning_failed', 'cancellation_pending', 'terminated', 'cancelled'];

    public function web(): string
    {
        return $this->listing('web', 'Web hosting');
    }

    public function email(): string
    {
        return $this->listing('email', 'Email hosting');
    }

    private function listing(string $type, string $title): string
    {
        $q = (string) Request::query('q', '');
        $status = (string) Request::query('status', '');
        $where = ['s.type = ?'];
        $params = [$type];
        if ($q !== '') {
            $where[] = '(s.domain LIKE ? OR c.full_name LIKE ? OR c.business_name LIKE ? OR s.provider_username LIKE ?)';
            array_push($params, ...array_fill(0, 4, '%' . $q . '%'));
        }
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        $base = "SELECT s.*, c.full_name, c.business_name, p.name AS plan_name, hp.name AS provider_name
                 FROM services s
                 JOIN customers c ON c.id = s.customer_id
                 JOIN hosting_plans p ON p.id = s.plan_id
                 LEFT JOIN hosting_providers hp ON hp.id = s.provider_id
                 WHERE " . implode(' AND ', $where);
        $total = (int) db()->fetchColumn("SELECT COUNT(*) FROM ({$base}) t", $params);
        $p = paginate($total, 25);
        $services = db()->fetchAll($base . " ORDER BY FIELD(s.status,'provisioning_failed','pending_payment','provisioning','suspended','active','cancellation_pending','terminated','cancelled'), s.next_due_date LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);
        $stats = db()->fetch("SELECT COUNT(*) AS total, SUM(status = 'active') AS active, SUM(status = 'suspended') AS suspended, SUM(status IN ('pending_payment','provisioning','provisioning_failed')) AS pending FROM services WHERE type = ?", [$type]);
        return $this->view('admin.services.index', compact('services', 'p', 'stats', 'q', 'status') + ['title' => $title, 'type' => $type]);
    }

    public function create(): string
    {
        $customerId = (int) Request::query('customer_id', 0);
        $plans = db()->fetchAll("SELECT * FROM hosting_plans WHERE status IN ('active','hidden') ORDER BY type, sort_order, name");
        if (!$plans) {
            flash('error', 'Create a hosting plan first.');
            redirect('/hosting/plans');
        }
        return $this->view('admin.services.form', [
            'title' => 'Add service',
            'plans' => $plans,
            'customers' => db()->fetchAll("SELECT id, full_name, business_name, email FROM customers WHERE status = 'active' ORDER BY business_name, full_name"),
            'customerId' => $customerId,
        ]);
    }

    public function store(): never
    {
        $data = $this->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'plan_id' => 'required|integer|exists:hosting_plans,id',
            'domain' => 'required|domain',
            'billing_cycle' => 'required|in:monthly,quarterly,semiannual,annual',
            'price' => 'numeric|min:0|max:999999',
            'notes' => 'max:5000',
        ], ['customer_id' => 'Customer', 'plan_id' => 'Hosting plan']);
        try {
            $r = BillingService::createOrder((int) $data['customer_id'], (int) $data['plan_id'], $data['domain'], $data['billing_cycle'], [
                'price' => $data['price'] !== '' ? $data['price'] : null,
                'notes' => $data['notes'] ?: null,
                'allow_hidden' => true,
            ]);
        } catch (\InvalidArgumentException $e) {
            back_with_errors(['domain' => $e->getMessage()], '/hosting/create');
        }
        audit('service.created', 'service', $r['service_id'], ['domain' => $data['domain'], 'invoice_id' => $r['invoice_id']]);
        flash('success', 'Service order created. Invoice ' . $this->invoiceNumber($r['invoice_id']) . ' is awaiting payment.');
        redirect('/hosting/view/' . $r['service_id']);
    }

    public function show(int $id): string
    {
        return $this->view('admin.services.show', $this->showData($id));
    }

    public function update(int $id): never
    {
        $s = $this->service($id);
        $data = $this->validate([
            'domain' => 'required|domain',
            'billing_cycle' => 'required|in:monthly,quarterly,semiannual,annual',
            'price' => 'required|numeric|min:0|max:999999',
            'next_due_date' => 'date',
            'domain_expiry_date' => 'date',
            'domain_registrar' => 'max:120',
            'notes' => 'max:5000',
        ]);
        $domain = strtolower(trim($data['domain']));
        if ($domain !== $s['domain'] && db()->fetchColumn("SELECT COUNT(*) FROM services WHERE domain = ? AND type = ? AND status NOT IN ('terminated','cancelled') AND id <> ?", [$domain, $s['type'], $id])) {
            back_with_errors(['domain' => "A {$s['type']} service for {$domain} already exists."], '/hosting/view/' . $id);
        }
        $changes = [];
        foreach (['domain' => $domain, 'billing_cycle' => $data['billing_cycle'], 'price' => $data['price'], 'next_due_date' => $data['next_due_date'], 'domain_expiry_date' => $data['domain_expiry_date'], 'domain_registrar' => $data['domain_registrar'], 'notes' => $data['notes']] as $k => $v) {
            $v = $v === '' ? null : $v;
            if ((string) ($s[$k] ?? '') !== (string) $v) {
                $changes[$k] = ['from' => $s[$k], 'to' => $v];
            }
            $s[$k] = $v;
        }
        db()->update('services', [
            'domain' => $s['domain'], 'billing_cycle' => $s['billing_cycle'], 'price' => $s['price'],
            'next_due_date' => $s['next_due_date'], 'domain_expiry_date' => $s['domain_expiry_date'],
            'domain_registrar' => $s['domain_registrar'], 'notes' => $s['notes'], 'updated_at' => now(),
        ], 'id = :id', ['id' => $id]);
        if (isset($changes['price'])) {
            BillingService::repriceOpenRenewal($id);
        }
        if ($changes) {
            audit('service.updated', 'service', $id, $changes);
        }
        flash('success', 'Service saved.');
        redirect('/hosting/view/' . $id);
    }

    public function provision(int $id): never
    {
        $this->respond(ProvisioningService::provision($id, automated: false), '/hosting/view/' . $id);
    }

    public function suspend(int $id): never
    {
        $reason = trim((string) Request::input('reason', ''));
        if ($reason === '') {
            back_with_errors(['reason' => 'A suspension reason is required.'], '/hosting/view/' . $id);
        }
        $this->respond(ProvisioningService::suspend($id, $reason, 'manual'), '/hosting/view/' . $id);
    }

    public function unsuspend(int $id): never
    {
        $this->respond(ProvisioningService::unsuspend($id, 'Manual reactivation'), '/hosting/view/' . $id);
    }

    /**
     * Change the plan. Upgrades create a pro-rata invoice (applied on payment);
     * equal/cheaper plans are applied through the provider immediately.
     */
    public function changePlan(int $id): never
    {
        $s = $this->service($id);
        $planId = (int) Request::input('plan_id', 0);
        try {
            $this->planOrFail($planId, $s);
            $r = BillingService::requestPlanChange($id, $planId);
            if ($r['invoice_id']) {
                flash('success', 'Upgrade invoice ' . $this->invoiceNumber($r['invoice_id']) . ' created. The plan changes when it is paid.');
            } else {
                flash($r['result']['success'] ? 'success' : 'error', $r['result']['message']);
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/hosting/view/' . $id);
    }

    public function sync(int $id): never
    {
        $this->respond(ProvisioningService::syncService($id), '/hosting/view/' . $id);
    }

    public function link(int $id): never
    {
        $providerId = (int) Request::input('provider_id', 0);
        $username = trim((string) Request::input('username', ''));
        if (!$providerId || $username === '') {
            back_with_errors(['username' => 'Choose a provider and enter the account username.'], '/hosting/view/' . $id);
        }
        $this->respond(ProvisioningService::linkAccount($id, $providerId, $username), '/hosting/view/' . $id);
    }

    /** Redirects to the provider's single sign-on URL. */
    public function login(int $id): never
    {
        $app = Request::input('app');
        $r = ProvisioningService::loginUrl($id, 'cpanel', $app !== null && $app !== '' ? (string) $app : null);
        if (!$r['success']) {
            flash('error', $r['message']);
            redirect('/hosting/view/' . $id);
        }
        header('Location: ' . $r['url'], true, 302);
        exit;
    }

    /** Generate the renewal invoice immediately (for early renewals / manual billing). */
    public function renew(int $id): never
    {
        $invoiceId = BillingService::generateRenewal($id, force: true);
        if ($invoiceId) {
            audit('service.renewal_invoiced', 'service', $id, ['invoice_id' => $invoiceId]);
            flash('success', 'Renewal invoice ' . $this->invoiceNumber($invoiceId) . ' is ready.');
        } else {
            flash('error', 'No renewal invoice is due for this service (check status and next due date).');
        }
        redirect('/hosting/view/' . $id);
    }

    /**
     * Cancel at the end of the paid period (cancellation_pending) or, when the
     * service was never active, cancel it outright.
     */
    public function cancel(int $id): never
    {
        $s = $this->service($id);
        if (in_array($s['status'], ['active', 'suspended'], true)) {
            db()->update('services', ['status' => 'cancellation_pending', 'termination_flagged_at' => $s['next_due_date'] ?: now(), 'updated_at' => now()], 'id = :id', ['id' => $id]);
            audit('service.cancellation_requested', 'service', $id);
            flash('success', 'Service will be terminated at the end of the paid period (' . date_fmt($s['next_due_date']) . ').');
        } elseif (in_array($s['status'], ['pending_payment', 'provisioning', 'provisioning_failed', 'cancellation_pending'], true)) {
            db()->update('services', ['status' => 'cancelled', 'updated_at' => now()], 'id = :id', ['id' => $id]);
            audit('service.cancelled', 'service', $id);
            flash('success', 'Service cancelled.');
        } else {
            flash('error', 'This service is already ' . status_label($s['status']) . '.');
        }
        redirect('/hosting/view/' . $id);
    }

    public function terminate(int $id): never
    {
        $this->respond(ProvisioningService::terminate($id), '/hosting/view/' . $id);
    }

    private function respond(array $r, string $redirectTo): never
    {
        flash($r['success'] ? 'success' : 'error', $r['message']);
        redirect($redirectTo);
    }

    private function service(int $id): array
    {
        $s = db()->fetch(
            'SELECT s.*, c.full_name, c.business_name, c.email AS customer_email, p.name AS plan_name, p.provider_package AS plan_package, hp.name AS provider_name
             FROM services s JOIN customers c ON c.id = s.customer_id JOIN hosting_plans p ON p.id = s.plan_id LEFT JOIN hosting_providers hp ON hp.id = s.provider_id
             WHERE s.id = ?',
            [$id]
        );
        if (!$s) {
            abort(404);
        }
        return $s;
    }

    private function showData(int $id): array
    {
        $s = $this->service($id);
        return [
            'title' => $s['domain'],
            's' => $s,
            'items' => db()->fetchAll('SELECT ii.*, i.number, i.status AS invoice_status, i.due_date FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE ii.service_id = ? ORDER BY i.id DESC LIMIT 30', [$id]),
            'logs' => db()->fetchAll("SELECT * FROM automation_logs WHERE entity_type = 'service' AND entity_id = ? ORDER BY id DESC LIMIT 30", [$id]),
            'altPlans' => db()->fetchAll("SELECT id, name FROM hosting_plans WHERE type = ? AND status = 'active' AND provider_id <=> ? AND id <> ? ORDER BY sort_order, name", [$s['type'], $s['provider_id'], $s['plan_id']]),
            'providers' => db()->fetchAll("SELECT id, name FROM hosting_providers WHERE status = 'active' ORDER BY name"),
        ];
    }

    private function planOrFail(int $planId, array $s): array
    {
        $plan = db()->fetch("SELECT * FROM hosting_plans WHERE id = ? AND status = 'active'", [$planId]);
        if (!$plan || $plan['type'] !== $s['type']) {
            throw new \InvalidArgumentException('The selected plan is not compatible with this service.');
        }
        return $plan;
    }

    private function invoiceNumber(int $invoiceId): string
    {
        return (string) (db()->fetchColumn('SELECT number FROM invoices WHERE id = ?', [$invoiceId]) ?: '#' . $invoiceId);
    }
}
