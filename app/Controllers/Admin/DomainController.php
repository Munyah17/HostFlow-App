<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Services\DomainService;

final class DomainController extends Controller
{
    public function index(): string
    {
        $q = (string) Request::query('q', '');
        $status = (string) Request::query('status', '');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(d.domain LIKE ? OR c.full_name LIKE ? OR c.business_name LIKE ? OR d.registrar LIKE ?)';
            array_push($params, ...array_fill(0, 4, '%' . $q . '%'));
        }
        if (in_array($status, DomainService::STATUSES, true)) {
            $where[] = 'd.status = ?';
            $params[] = $status;
        }
        $base = "SELECT d.*, c.full_name, c.business_name
                 FROM domains d JOIN customers c ON c.id = d.customer_id
                 WHERE " . implode(' AND ', $where);
        $total = (int) db()->fetchColumn("SELECT COUNT(*) FROM ({$base}) t", $params);
        $p = paginate($total, 25);
        $domains = db()->fetchAll($base . " ORDER BY FIELD(d.status,'pending_registration','pending_payment','expired','active','transferred_away','cancelled'), d.expiry_date LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);
        $stats = db()->fetch("SELECT COUNT(*) AS total, SUM(status = 'active') AS active, SUM(status = 'pending_registration') AS pending, SUM(status = 'expired') AS expired, SUM(status = 'active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS expiring FROM domains");
        return $this->view('admin.domains.index', compact('domains', 'p', 'stats', 'q', 'status') + ['title' => 'Domains']);
    }

    public function create(): string
    {
        return $this->view('admin.domains.form', [
            'title' => 'Add domain',
            'customers' => db()->fetchAll("SELECT id, full_name, business_name, email FROM customers WHERE status = 'active' ORDER BY business_name, full_name"),
            'services' => db()->fetchAll("SELECT s.id, s.domain, c.business_name, c.full_name FROM services s JOIN customers c ON c.id = s.customer_id WHERE s.status NOT IN ('terminated','cancelled') ORDER BY s.domain"),
            'pricing' => db()->fetchAll("SELECT * FROM domain_pricing WHERE status = 'active' ORDER BY sort_order, tld"),
            'customerId' => (int) Request::query('customer_id', 0),
        ]);
    }

    /** Create a domain order (invoice) or register an already-active domain. */
    public function store(): never
    {
        $data = $this->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'domain' => 'required|domain',
            'years' => 'integer|min:1|max:10',
            'service_id' => 'integer|exists:services,id',
            'price' => 'numeric|min:0|max:999999',
            'renewal_price' => 'numeric|min:0|max:999999',
            'registered_at' => 'date',
            'expiry_date' => 'date',
            'registrar' => 'max:120',
            'registrar_reference' => 'max:190',
            'nameservers' => 'max:500',
            'notes' => 'max:5000',
        ], ['customer_id' => 'Customer', 'service_id' => 'Linked service']);
        $alreadyRegistered = !empty($_POST['already_registered']);
        try {
            if ($alreadyRegistered) {
                $id = DomainService::createManual((int) $data['customer_id'], $data['domain'], [
                    'service_id' => $data['service_id'] !== '' ? (int) $data['service_id'] : null,
                    'registrar' => $data['registrar'] ?: null,
                    'registrar_reference' => $data['registrar_reference'] ?: null,
                    'nameservers' => $data['nameservers'] ?: null,
                    'registered_at' => $data['registered_at'] ?: today(),
                    'expiry_date' => $data['expiry_date'] ?: null,
                    'auto_renew' => !empty($_POST['auto_renew']),
                    'registration_price' => $data['price'],
                    'renewal_price' => $data['renewal_price'],
                    'notes' => $data['notes'] ?: null,
                ]);
                audit('domain.added', 'domain', $id, ['domain' => $data['domain']]);
                flash('success', 'Domain added as registered.');
                redirect('/domains/view/' . $id);
            }
            $r = DomainService::order((int) $data['customer_id'], $data['domain'], (int) ($data['years'] ?: 1), [
                'service_id' => $data['service_id'] !== '' ? (int) $data['service_id'] : null,
                'price' => $data['price'],
                'auto_renew' => true,
                'notes' => $data['notes'] ?: null,
            ]);
        } catch (\InvalidArgumentException $e) {
            back_with_errors(['domain' => $e->getMessage()], '/domains/create');
        }
        audit('domain.order_created', 'domain', $r['domain_id'], ['domain' => $data['domain'], 'invoice_id' => $r['invoice_id']]);
        flash('success', 'Domain order created. Invoice ' . $this->invoiceNumber($r['invoice_id']) . ' is awaiting payment.');
        redirect('/domains/view/' . $r['domain_id']);
    }

    public function show(int $id): string
    {
        $d = $this->domain($id);
        return $this->view('admin.domains.show', [
            'title' => $d['domain'],
            'd' => $d,
            'items' => db()->fetchAll('SELECT ii.*, i.number, i.status AS invoice_status, i.due_date FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE ii.domain_id = ? ORDER BY i.id DESC LIMIT 30', [$id]),
            'logs' => db()->fetchAll("SELECT * FROM automation_logs WHERE entity_type = 'domain' AND entity_id = ? ORDER BY id DESC LIMIT 30", [$id]),
            'services' => db()->fetchAll("SELECT s.id, s.domain FROM services s WHERE s.customer_id = ? AND s.status NOT IN ('terminated','cancelled') ORDER BY s.domain", [$d['customer_id']]),
        ]);
    }

    public function update(int $id): never
    {
        $d = $this->domain($id);
        $data = $this->validate([
            'service_id' => 'integer|exists:services,id',
            'registrar' => 'max:120',
            'registrar_reference' => 'max:190',
            'nameservers' => 'max:500',
            'registered_at' => 'date',
            'expiry_date' => 'date',
            'renewal_price' => 'numeric|min:0|max:999999',
            'notes' => 'max:5000',
        ]);
        db()->update('domains', [
            'service_id' => $data['service_id'] !== '' ? (int) $data['service_id'] : null,
            'registrar' => $data['registrar'] ?: null,
            'registrar_reference' => $data['registrar_reference'] ?: null,
            'nameservers' => $data['nameservers'] ?: null,
            'registered_at' => $data['registered_at'] ?: null,
            'expiry_date' => $data['expiry_date'] ?: null,
            'renewal_price' => $data['renewal_price'] !== '' ? round((float) $data['renewal_price'], 2) : $d['renewal_price'],
            'auto_renew' => !empty($_POST['auto_renew']) ? 1 : 0,
            'notes' => $data['notes'] ?: null,
            'updated_at' => now(),
        ], 'id = :id', ['id' => $id]);
        audit('domain.updated', 'domain', $id, ['domain' => $d['domain']]);
        flash('success', 'Domain saved.');
        redirect('/domains/view/' . $id);
    }

    /** Confirm registration at the registrar — activates the domain and starts the expiry clock. */
    public function register(int $id): never
    {
        $data = $this->validate([
            'registered_at' => 'date',
            'expiry_date' => 'date',
            'registrar' => 'max:120',
            'registrar_reference' => 'max:190',
            'nameservers' => 'max:500',
        ]);
        try {
            DomainService::markRegistered($id, $data);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/domains/view/' . $id);
        }
        audit('domain.registered', 'domain', $id);
        flash('success', 'Domain marked as registered.');
        redirect('/domains/view/' . $id);
    }

    /** Generate the renewal invoice immediately. */
    public function renew(int $id): never
    {
        $invoiceId = DomainService::generateRenewal($id, force: true);
        if ($invoiceId) {
            audit('domain.renewal_invoiced', 'domain', $id, ['invoice_id' => $invoiceId]);
            flash('success', 'Renewal invoice ' . $this->invoiceNumber($invoiceId) . ' is ready.');
        } else {
            flash('error', 'No renewal invoice could be generated (check status, expiry date and renewal price).');
        }
        redirect('/domains/view/' . $id);
    }

    /** Mark as transferred away or cancelled. */
    public function setStatus(int $id): never
    {
        $d = $this->domain($id);
        $target = (string) Request::input('status', '');
        if (!in_array($target, ['transferred_away', 'cancelled'], true)) {
            flash('error', 'Invalid status.');
            redirect('/domains/view/' . $id);
        }
        if (in_array($d['status'], ['transferred_away', 'cancelled'], true)) {
            flash('error', 'This domain is already ' . status_label($d['status']) . '.');
            redirect('/domains/view/' . $id);
        }
        db()->update('domains', ['status' => $target, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        audit('domain.' . $target, 'domain', $id, ['domain' => $d['domain']]);
        flash('success', 'Domain marked as ' . status_label($target) . '.');
        redirect('/domains/view/' . $id);
    }

    /** TLD pricing management. */
    public function pricing(): string
    {
        return $this->view('admin.domains.pricing', [
            'title' => 'Domain pricing',
            'pricing' => db()->fetchAll('SELECT * FROM domain_pricing ORDER BY sort_order, tld'),
            'canEdit' => Auth::isSuper(),
        ]);
    }

    public function pricingStore(): never
    {
        $data = $this->validate([
            'tld' => 'required|regex:/^\.[a-z0-9.\-]{1,29}$/|max:30',
            'register_price' => 'required|numeric|min:0|max:999999',
            'renew_price' => 'required|numeric|min:0|max:999999',
            'transfer_price' => 'numeric|min:0|max:999999',
        ], ['tld' => 'Extension']);
        $tld = strtolower($data['tld']);
        if (db()->fetchColumn('SELECT COUNT(*) FROM domain_pricing WHERE tld = ?', [$tld])) {
            back_with_errors(['tld' => "{$tld} already has a price."], '/domains/pricing');
        }
        $id = db()->insert('domain_pricing', [
            'tld' => $tld,
            'register_price' => round((float) $data['register_price'], 2),
            'renew_price' => round((float) $data['renew_price'], 2),
            'transfer_price' => $data['transfer_price'] !== '' ? round((float) $data['transfer_price'], 2) : null,
            'status' => 'active',
            'sort_order' => (int) db()->fetchColumn('SELECT COALESCE(MAX(sort_order),0) + 1 FROM domain_pricing'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        audit('domain_pricing.created', 'domain_pricing', $id, ['tld' => $tld]);
        flash('success', "{$tld} pricing added.");
        redirect('/domains/pricing');
    }

    public function pricingUpdate(int $id): never
    {
        $data = $this->validate([
            'register_price' => 'required|numeric|min:0|max:999999',
            'renew_price' => 'required|numeric|min:0|max:999999',
            'transfer_price' => 'numeric|min:0|max:999999',
            'status' => 'required|in:active,hidden',
        ]);
        db()->update('domain_pricing', [
            'register_price' => round((float) $data['register_price'], 2),
            'renew_price' => round((float) $data['renew_price'], 2),
            'transfer_price' => $data['transfer_price'] !== '' ? round((float) $data['transfer_price'], 2) : null,
            'status' => $data['status'],
            'updated_at' => now(),
        ], 'id = :id', ['id' => $id]);
        audit('domain_pricing.updated', 'domain_pricing', $id);
        flash('success', 'Pricing saved.');
        redirect('/domains/pricing');
    }

    public function pricingDelete(int $id): never
    {
        $row = $this->findOrFail('domain_pricing', $id);
        db()->query('DELETE FROM domain_pricing WHERE id = ?', [$id]);
        audit('domain_pricing.deleted', 'domain_pricing', $id, ['tld' => $row['tld']]);
        flash('success', "{$row['tld']} pricing removed.");
        redirect('/domains/pricing');
    }

    private function domain(int $id): array
    {
        $d = db()->fetch(
            'SELECT d.*, c.full_name, c.business_name, c.email AS customer_email, s.domain AS service_domain
             FROM domains d JOIN customers c ON c.id = d.customer_id LEFT JOIN services s ON s.id = d.service_id
             WHERE d.id = ?',
            [$id]
        );
        if (!$d) {
            abort(404);
        }
        return $d;
    }

    private function invoiceNumber(int $invoiceId): string
    {
        return (string) (db()->fetchColumn('SELECT number FROM invoices WHERE id = ?', [$invoiceId]) ?: '#' . $invoiceId);
    }
}
