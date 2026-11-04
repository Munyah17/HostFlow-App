<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Validator;
use App\Services\InvoiceService;
use App\Services\PaymentService;

final class PaymentController extends Controller
{
    public function index(): string
    {
        $q = (string) Request::query('q', '');
        $status = (string) Request::query('status', '');
        $gateway = (string) Request::query('gateway', '');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(p.reference LIKE ? OR p.gateway_reference LIKE ? OR i.number LIKE ? OR c.full_name LIKE ? OR c.business_name LIKE ?)';
            array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
        }
        if (in_array($status, ['pending', 'completed', 'failed', 'cancelled', 'refunded'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($gateway !== '') {
            $where[] = 'p.gateway = ?';
            $params[] = $gateway;
        }
        $base = 'SELECT p.*, i.number, c.full_name, c.business_name FROM payments p JOIN invoices i ON i.id = p.invoice_id JOIN customers c ON c.id = p.customer_id WHERE ' . implode(' AND ', $where);
        $total = (int) db()->fetchColumn("SELECT COUNT(*) FROM ({$base}) t", $params);
        $p = paginate($total, 25);
        $payments = db()->fetchAll($base . " ORDER BY p.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);
        $stats = db()->fetch(
            "SELECT COALESCE(SUM(IF(status = 'completed' AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01'), amount, 0)),0) AS month,
                    SUM(status = 'pending') AS pending,
                    COALESCE(SUM(IF(status = 'completed' AND paid_at >= CURDATE(), amount, 0)),0) AS today,
                    SUM(status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS failed7
             FROM payments"
        );
        return $this->view('admin.payments.index', compact('payments', 'p', 'stats', 'q', 'status', 'gateway') + [
            'title' => 'Payments',
            'gateways' => db()->fetchAll('SELECT DISTINCT gateway FROM payments ORDER BY gateway'),
            'payableInvoices' => db()->fetchAll("SELECT i.id, i.number, c.full_name, c.business_name, i.total - i.amount_paid AS balance, i.currency FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.status IN ('pending','overdue') AND i.total > i.amount_paid ORDER BY i.due_date LIMIT 200"),
        ]);
    }

    /** Record a cash / bank / manual mobile-money payment against an open invoice. */
    public function manual(): never
    {
        $data = $this->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01|max:999999',
            'method' => 'required|in:' . implode(',', array_keys(PaymentService::MANUAL_METHODS)),
            'reference' => 'required|max:120',
            'paid_at' => 'required|date',
            'note' => 'max:500',
        ], ['invoice_id' => 'Invoice', 'paid_at' => 'Payment date']);
        try {
            $id = PaymentService::recordManual((int) $data['invoice_id'], (float) $data['amount'], $data['method'], $data['reference'], $data['paid_at'], $data['note'] ?: null, Auth::id());
        } catch (\InvalidArgumentException $e) {
            back_with_errors(['reference' => $e->getMessage()], '/payments');
        }
        flash('success', 'Payment of ' . money((float) $data['amount']) . ' recorded.');
        redirect('/billing/invoices/view/' . (int) $data['invoice_id']);
    }

    /** Re-check a pending gateway payment with the provider (Paynow poll). */
    public function verify(int $id): never
    {
        try {
            $p = PaymentService::verify($id);
            flash($p['status'] === 'completed' ? 'success' : 'info', 'Payment ' . $p['reference'] . ' is ' . status_label($p['status']) . '.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/payments');
    }
}
