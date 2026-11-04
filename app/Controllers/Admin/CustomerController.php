<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Services\InvoiceService;
use App\Services\Notifications\NotificationService;

final class CustomerController extends Controller
{
    private const RULES = [
        'full_name' => 'required|max:150',
        'business_name' => 'max:150',
        'email' => 'required|email|max:190',
        'phone' => 'max:40',
        'address' => 'max:255',
        'city' => 'max:100',
        'country' => 'required|max:100',
        'notes' => 'max:5000',
    ];

    public function index(): string
    {
        $q = (string) Request::query('q', '');
        $status = (string) Request::query('status', '');
        $balance = (string) Request::query('balance', '');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(c.full_name LIKE ? OR c.business_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR EXISTS (SELECT 1 FROM services s WHERE s.customer_id = c.id AND s.domain LIKE ?))';
            array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
        }
        if (in_array($status, ['active', 'disabled'], true)) {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }
        $having = $balance === 'outstanding' ? 'HAVING balance > 0' : ($balance === 'overdue' ? 'HAVING overdue > 0' : '');
        $base = "SELECT c.*,
                  (SELECT COALESCE(SUM(i.total - i.amount_paid),0) FROM invoices i WHERE i.customer_id = c.id AND i.status IN ('pending','overdue')) AS balance,
                  (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'overdue') AS overdue,
                  (SELECT COUNT(*) FROM services s WHERE s.customer_id = c.id AND s.status IN ('active','suspended')) AS services,
                  (SELECT GROUP_CONCAT(DISTINCT s.domain ORDER BY s.id SEPARATOR ', ') FROM services s WHERE s.customer_id = c.id AND s.status NOT IN ('terminated','cancelled')) AS domains
                 FROM customers c WHERE " . implode(' AND ', $where) . " {$having}";
        $total = (int) db()->fetchColumn("SELECT COUNT(*) FROM ({$base}) t", $params);
        $p = paginate($total, 25);
        $customers = db()->fetchAll($base . " ORDER BY c.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);
        $stats = db()->fetch(
            "SELECT COUNT(*) AS total, SUM(status = 'active') AS active,
              (SELECT COALESCE(SUM(total - amount_paid),0) FROM invoices WHERE status IN ('pending','overdue')) AS outstanding,
              (SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS new30
             FROM customers"
        );
        return $this->view('admin.customers.index', ['title' => 'Customers', 'customers' => $customers, 'p' => $p, 'stats' => $stats, 'q' => $q, 'status' => $status, 'balance' => $balance]);
    }

    public function create(): string
    {
        return $this->view('admin.customers.form', ['title' => 'Add customer', 'customer' => null]);
    }

    public function store(): never
    {
        $data = $this->validate(self::RULES);
        $data['email'] = strtolower($data['email']);
        if (db()->fetchColumn('SELECT id FROM customers WHERE email = ?', [$data['email']])) {
            back_with_errors(['email' => 'A customer with this email address already exists.']);
        }
        $createLogin = !empty($_POST['create_login']);
        if ($createLogin && db()->fetchColumn('SELECT id FROM users WHERE email = ?', [$data['email']])) {
            back_with_errors(['email' => 'A user account with this email address already exists.']);
        }
        $id = db()->transaction(function ($db) use ($data, $createLogin) {
            $id = $db->insert('customers', array_map(fn ($v) => $v === '' ? null : $v, $data) + ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            if ($createLogin) {
                $this->createPortalUser($id, $data['full_name'], $data['email']);
            }
            return $id;
        });
        audit('customer.created', 'customer', $id, ['email' => $data['email']]);
        if ($createLogin) {
            NotificationService::notifyCustomer($id, 'welcome', [], 'welcome:' . $id);
            $this->sendSetPasswordLink($id);
        }
        flash('success', 'Customer created.' . ($createLogin ? ' A portal invitation has been queued.' : ''));
        redirect('/customers/view/' . $id);
    }

    public function show(int $id): string
    {
        $customer = $this->findOrFail('customers', $id);
        $tab = (string) Request::query('tab', 'overview');
        return $this->view('admin.customers.show', [
            'title' => $customer['business_name'] ?: $customer['full_name'],
            'customer' => $customer,
            'tab' => $tab,
            'user' => db()->fetch('SELECT * FROM users WHERE customer_id = ?', [$id]),
            'balance' => InvoiceService::customerBalance($id),
            'services' => db()->fetchAll('SELECT s.*, p.name AS plan_name FROM services s JOIN hosting_plans p ON p.id = s.plan_id WHERE s.customer_id = ? ORDER BY FIELD(s.status, "active","suspended","provisioning_failed","provisioning","pending_payment") DESC, s.created_at DESC', [$id]),
            'invoices' => db()->fetchAll('SELECT * FROM invoices WHERE customer_id = ? ORDER BY id DESC LIMIT 100', [$id]),
            'payments' => db()->fetchAll('SELECT p.*, i.number FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.customer_id = ? ORDER BY p.created_at DESC LIMIT 100', [$id]),
            'tickets' => db()->fetchAll('SELECT * FROM support_tickets WHERE customer_id = ? ORDER BY updated_at DESC LIMIT 50', [$id]),
            'notes' => db()->fetchAll('SELECT n.*, u.name AS author FROM customer_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.customer_id = ? ORDER BY n.created_at DESC', [$id]),
            'activity' => db()->fetchAll(
                "SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                 WHERE (a.entity_type = 'customer' AND a.entity_id = ?)
                    OR (a.entity_type = 'service' AND a.entity_id IN (SELECT id FROM services WHERE customer_id = ?))
                    OR (a.entity_type = 'invoice' AND a.entity_id IN (SELECT id FROM invoices WHERE customer_id = ?))
                 ORDER BY a.id DESC LIMIT 50",
                [$id, $id, $id]
            ),
        ]);
    }

    public function edit(int $id): string
    {
        $customer = $this->findOrFail('customers', $id);
        return $this->view('admin.customers.form', ['title' => 'Edit customer', 'customer' => $customer]);
    }

    public function update(int $id): never
    {
        $customer = $this->findOrFail('customers', $id);
        $data = $this->validate(self::RULES);
        $data['email'] = strtolower($data['email']);
        if (db()->fetchColumn('SELECT id FROM customers WHERE email = ? AND id <> ?', [$data['email'], $id])) {
            back_with_errors(['email' => 'Another customer already uses this email address.']);
        }
        $user = db()->fetch('SELECT * FROM users WHERE customer_id = ?', [$id]);
        if ($user && $data['email'] !== $user['email'] && db()->fetchColumn('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $user['id']])) {
            back_with_errors(['email' => 'Another user account already uses this email address.']);
        }
        db()->transaction(function ($db) use ($id, $data, $user) {
            $db->update('customers', array_map(fn ($v) => $v === '' ? null : $v, $data) + ['updated_at' => now()], 'id = :id', ['id' => $id]);
            if ($user) {
                $db->update('users', ['email' => $data['email'], 'name' => $data['full_name'], 'updated_at' => now()], 'id = :id', ['id' => $user['id']]);
            }
        });
        $changes = [];
        foreach ($data as $k => $v) {
            if ((string) $customer[$k] !== (string) $v && $k !== 'notes') {
                $changes[$k] = ['from' => $customer[$k], 'to' => $v];
            }
        }
        audit('customer.updated', 'customer', $id, $changes);
        flash('success', 'Customer details saved.');
        redirect('/customers/view/' . $id);
    }

    public function addNote(int $id): never
    {
        $this->findOrFail('customers', $id);
        $note = trim((string) Request::input('note', ''));
        if ($note === '' || mb_strlen($note) > 5000) {
            back_with_errors(['note' => 'Enter a note (max 5000 characters).']);
        }
        db()->insert('customer_notes', ['customer_id' => $id, 'user_id' => Auth::id(), 'note' => $note, 'created_at' => now()]);
        audit('customer.note_added', 'customer', $id);
        flash('success', 'Note added.');
        redirect('/customers/view/' . $id, ['tab' => 'notes']);
    }

    public function toggleStatus(int $id): never
    {
        $customer = $this->findOrFail('customers', $id);
        $new = $customer['status'] === 'active' ? 'disabled' : 'active';
        db()->update('customers', ['status' => $new, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        audit('customer.' . ($new === 'active' ? 'enabled' : 'disabled'), 'customer', $id);
        flash('success', $new === 'active' ? 'Customer portal access enabled.' : 'Customer access disabled. Their hosting services are not affected.');
        redirect('/customers/view/' . $id);
    }

    /** Create a portal login or (re)send a set-password link. */
    public function portalAccess(int $id): never
    {
        $customer = $this->findOrFail('customers', $id);
        $user = db()->fetch('SELECT * FROM users WHERE customer_id = ?', [$id]);
        if (!$user) {
            if (db()->fetchColumn('SELECT id FROM users WHERE email = ?', [$customer['email']])) {
                flash('error', 'A user account with this email already exists.');
                redirect('/customers/view/' . $id);
            }
            $this->createPortalUser($id, $customer['full_name'], $customer['email']);
            audit('customer.portal_created', 'customer', $id);
        }
        $this->sendSetPasswordLink($id);
        flash('success', 'A password set-up link has been emailed to ' . $customer['email'] . '.');
        redirect('/customers/view/' . $id);
    }

    private function createPortalUser(int $customerId, string $name, string $email): int
    {
        return db()->insert('users', [
            'role' => Auth::CLIENT,
            'customer_id' => $customerId,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sendSetPasswordLink(int $customerId): void
    {
        $user = db()->fetch('SELECT * FROM users WHERE customer_id = ?', [$customerId]);
        if (!$user) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        db()->insert('password_resets', ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 72 * 3600), 'created_at' => now()]);
        NotificationService::notifyEmail($user['email'], 'password_reset', ['name' => $user['name'], 'reset_url' => absolute_url('/reset-password/' . $token)], null, true, $customerId);
    }
}
