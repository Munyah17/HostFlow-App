<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Services\Notifications\EmailChannel;
use App\Services\Payments\GatewayRegistry;
use App\Services\ReportService;

final class DashboardController extends Controller
{
    public function index(): string
    {
        $counts = ReportService::counts();
        $data = [
            'title' => Auth::isSuper() ? 'Business overview' : 'Operations overview',
            'c' => $counts,
            'mrr' => ReportService::mrr(),
            'revenue' => ReportService::monthlyRevenue(12),
            'recentPayments' => ReportService::recentPayments(6),
            'recentCustomers' => db()->fetchAll('SELECT * FROM customers ORDER BY created_at DESC LIMIT 5'),
            'tickets' => db()->fetchAll("SELECT t.*, c.full_name FROM support_tickets t JOIN customers c ON c.id = t.customer_id WHERE t.status IN ('open','pending') ORDER BY FIELD(t.priority,'urgent','high','normal','low'), t.updated_at DESC LIMIT 6"),
            'lastRun' => ReportService::lastCronRun(),
            'attention' => $this->attention(),
        ];
        if (Auth::isSuper()) {
            $data['providers'] = db()->fetchAll('SELECT * FROM hosting_providers ORDER BY name');
            $data['mailEnabled'] = (new EmailChannel())->isEnabled();
            $data['paynowEnabled'] = GatewayRegistry::get('paynow')->isAvailable();
            $data['failures'] = db()->fetchAll("SELECT * FROM automation_logs WHERE status = 'failed' ORDER BY id DESC LIMIT 5");
            return $this->view('admin.dashboard.super', $data);
        }
        $data['pendingInvoices'] = db()->fetchAll("SELECT i.*, c.full_name FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.status IN ('pending','overdue') ORDER BY i.due_date LIMIT 8");
        return $this->view('admin.dashboard.admin', $data);
    }

    /** Items needing staff action. */
    private function attention(): array
    {
        return db()->fetchAll(
            "SELECT s.id, s.domain, s.status, s.sync_status, s.sync_message, s.last_error, s.termination_flagged_at, c.full_name,
                CASE
                  WHEN s.status = 'provisioning_failed' THEN 'Provisioning failed'
                  WHEN s.status = 'provisioning' THEN 'Awaiting provisioning'
                  WHEN s.termination_flagged_at IS NOT NULL AND s.status = 'suspended' THEN 'Termination review'
                  WHEN s.sync_status IN ('mismatch','missing') THEN 'Server discrepancy'
                END AS reason
             FROM services s JOIN customers c ON c.id = s.customer_id
             WHERE s.status IN ('provisioning_failed','provisioning')
                OR (s.termination_flagged_at IS NOT NULL AND s.status = 'suspended')
                OR (s.sync_status IN ('mismatch','missing') AND s.status NOT IN ('terminated','cancelled'))
             ORDER BY s.updated_at DESC LIMIT 10"
        );
    }
}
