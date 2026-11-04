<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;

final class DashboardController extends Controller
{
    public function index(): string
    {
        $cid = Auth::customerId();
        $stats = db()->fetch(
            "SELECT
               (SELECT COUNT(*) FROM services WHERE customer_id = ? AND status = 'active') AS active,
               (SELECT COUNT(*) FROM services WHERE customer_id = ? AND status = 'suspended') AS suspended,
               (SELECT COALESCE(SUM(total - amount_paid),0) FROM invoices WHERE customer_id = ? AND status IN ('pending','overdue')) AS balance,
               (SELECT COUNT(*) FROM invoices WHERE customer_id = ? AND status IN ('pending','overdue')) AS unpaid,
               (SELECT COUNT(*) FROM support_tickets WHERE customer_id = ? AND status IN ('open','pending')) AS tickets",
            [$cid, $cid, $cid, $cid, $cid]
        );
        return $this->view('client.dashboard.index', [
            'title' => 'Dashboard',
            'customer' => db()->fetch('SELECT * FROM customers WHERE id = ?', [$cid]),
            'stats' => $stats,
            'services' => db()->fetchAll("SELECT s.*, p.name AS plan_name FROM services s JOIN hosting_plans p ON p.id = s.plan_id WHERE s.customer_id = ? AND s.status NOT IN ('terminated','cancelled') ORDER BY s.next_due_date LIMIT 8", [$cid]),
            'invoices' => db()->fetchAll("SELECT * FROM invoices WHERE customer_id = ? AND status <> 'draft' ORDER BY id DESC LIMIT 6", [$cid]),
            'tickets' => db()->fetchAll("SELECT * FROM support_tickets WHERE customer_id = ? AND status IN ('open','pending') ORDER BY updated_at DESC LIMIT 5", [$cid]),
        ]);
    }
}
