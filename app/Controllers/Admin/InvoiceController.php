<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Services\InvoiceService;
use App\Services\Notifications\NotificationService;
use App\Services\PdfService;

final class InvoiceController extends Controller
{
    private const STATUSES = ['draft', 'pending', 'overdue', 'paid', 'cancelled'];

    public function index(): string
    {
        $q = (string) Request::query('q', '');
        $status = (string) Request::query('status', '');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(i.number LIKE ? OR c.full_name LIKE ? OR c.business_name LIKE ? OR c.email LIKE ?)';
            array_push($params, ...array_fill(0, 4, '%' . $q . '%'));
        }
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'i.status = ?';
            $params[] = $status;
        }
        $base = 'SELECT i.*, c.full_name, c.business_name FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE ' . implode(' AND ', $where);
        $total = (int) db()->fetchColumn("SELECT COUNT(*) FROM ({$base}) t", $params);
        $p = paginate($total, 25);
        $invoices = db()->fetchAll($base . " ORDER BY i.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);
        $stats = db()->fetch(
            "SELECT
               SUM(status IN ('pending','overdue')) AS open_count, COALESCE(SUM(IF(status IN ('pending','overdue'), total - amount_paid, 0)),0) AS open_amount,
               SUM(status = 'overdue') AS overdue_count, COALESCE(SUM(IF(status = 'overdue', total - amount_paid, 0)),0) AS overdue_amount,
               SUM(status = 'draft') AS drafts,
               COALESCE(SUM(IF(status = 'paid' AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01'), amount_paid, 0)),0) AS paid_month
             FROM invoices"
        );
        return $this->view('admin.invoices.index', compact('invoices', 'p', 'stats', 'q', 'status') + ['title' => 'Invoices']);
    }

    public function create(): string
    {
        $customerId = (int) Request::query('customer_id', 0);
        return $this->view('admin.invoices.form', [
            'title' => 'New invoice',
            'customers' => db()->fetchAll('SELECT id, full_name, business_name, email FROM customers ORDER BY business_name, full_name'),
            'services' => $customerId ? db()->fetchAll("SELECT id, domain, type FROM services WHERE customer_id = ? AND status NOT IN ('terminated','cancelled') ORDER BY domain", [$customerId]) : [],
            'customerId' => $customerId,
        ]);
    }

    public function store(): never
    {
        $data = $this->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'issue_date' => 'date',
            'due_date' => 'required|date',
            'discount' => 'numeric|min:0|max:999999',
            'notes' => 'max:5000',
        ], ['customer_id' => 'Customer', 'due_date' => 'Due date']);
        $descriptions = (array) ($_POST['item_desc'] ?? []);
        $quantities = (array) ($_POST['item_qty'] ?? []);
        $prices = (array) ($_POST['item_price'] ?? []);
        $serviceIds = (array) ($_POST['item_service'] ?? []);
        $items = [];
        $errors = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            $qty = trim((string) ($quantities[$i] ?? '1'));
            $price = trim((string) ($prices[$i] ?? ''));
            if ($desc === '' && $price === '') {
                continue;
            }
            if ($desc === '' || !is_numeric($qty) || (float) $qty <= 0 || !is_numeric($price) || (float) $price < 0) {
                $errors["item_{$i}"] = 'Each item needs a description and a valid quantity and price.';
                continue;
            }
            $serviceId = (int) ($serviceIds[$i] ?? 0);
            if ($serviceId && !db()->fetchColumn('SELECT COUNT(*) FROM services WHERE id = ? AND customer_id = ?', [$serviceId, (int) $data['customer_id']])) {
                $serviceId = 0;
            }
            $items[] = [
                'description' => $desc,
                'quantity' => (float) $qty,
                'unit_price' => (float) $price,
                'service_id' => $serviceId ?: null,
            ];
        }
        if ($errors) {
            back_with_errors($errors, '/billing/invoices/create');
        }
        if (!$items) {
            back_with_errors(['items' => 'Add at least one line item.'], '/billing/invoices/create');
        }
        try {
            $id = InvoiceService::create((int) $data['customer_id'], $items, [
                'issue_date' => $data['issue_date'] ?: today(),
                'due_date' => $data['due_date'],
                'discount' => $data['discount'] !== '' ? (float) $data['discount'] : 0,
                'notes' => $data['notes'] ?: null,
                'draft' => !empty($_POST['draft']),
            ]);
        } catch (\InvalidArgumentException $e) {
            back_with_errors(['items' => $e->getMessage()], '/billing/invoices/create');
        }
        audit('invoice.created', 'invoice', $id);
        if (empty($_POST['draft'])) {
            InvoiceService::notifyCreated($id);
            flash('success', 'Invoice created and queued for emailing to the customer.');
        } else {
            flash('success', 'Draft invoice created.');
        }
        redirect('/billing/invoices/view/' . $id);
    }

    public function show(int $id): string
    {
        $invoice = InvoiceService::find($id);
        if (!$invoice) {
            abort(404);
        }
        return $this->view('admin.invoices.show', [
            'title' => $invoice['number'],
            'invoice' => $invoice,
            'items' => InvoiceService::items($id),
            'payments' => db()->fetchAll('SELECT p.*, u.name AS recorded_by_name FROM payments p LEFT JOIN users u ON u.id = p.recorded_by WHERE p.invoice_id = ? ORDER BY p.id DESC', [$id]),
            'balance' => InvoiceService::balance($invoice),
            'payable' => InvoiceService::isPayable($invoice),
        ]);
    }

    /** Stream the invoice PDF. */
    public function pdf(int $id): never
    {
        if (!db()->fetchColumn('SELECT COUNT(*) FROM invoices WHERE id = ?', [$id])) {
            abort(404);
        }
        PdfService::sendInvoice($id);
    }

    public function publish(int $id): never
    {
        InvoiceService::publish($id);
        audit('invoice.published', 'invoice', $id);
        flash('success', 'Invoice published and queued for emailing to the customer.');
        redirect('/billing/invoices/view/' . $id);
    }

    public function cancel(int $id): never
    {
        $reason = trim((string) Request::input('reason', ''));
        try {
            InvoiceService::cancel($id, $reason);
            audit('invoice.cancelled', 'invoice', $id, $reason ? ['reason' => $reason] : null);
            flash('success', 'Invoice cancelled.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/billing/invoices/view/' . $id);
    }

    /** Resend the invoice notification email immediately. */
    public function send(int $id): never
    {
        $inv = InvoiceService::find($id);
        if (!$inv) {
            abort(404);
        }
        if ($inv['status'] === 'draft') {
            flash('error', 'Publish the invoice before sending it.');
            redirect('/billing/invoices/view/' . $id);
        }
        $queued = NotificationService::notifyCustomer((int) $inv['customer_id'], 'invoice_created', [
            'invoice_number' => $inv['number'],
            'total' => money($inv['total'], $inv['currency']),
            'due_date' => date_fmt($inv['due_date']),
            'invoice_url' => absolute_url('/client/invoices/' . $inv['id']),
        ], null, true);
        audit('invoice.sent', 'invoice', $id);
        flash($queued ? 'success' : 'error', $queued ? 'Invoice emailed to ' . $inv['email'] . '.' : 'Could not send — check SMTP settings and that the customer has an email address.');
        redirect('/billing/invoices/view/' . $id);
    }

    public function dueDate(int $id): never
    {
        $inv = InvoiceService::find($id);
        if (!$inv) {
            abort(404);
        }
        $date = (string) Request::input('due_date', '');
        if (!\App\Core\Validator::isDate($date)) {
            back_with_errors(['due_date' => 'Enter a valid date.'], '/billing/invoices/view/' . $id);
        }
        if (!in_array($inv['status'], ['pending', 'overdue'], true)) {
            flash('error', 'Only unpaid invoices can have their due date changed.');
            redirect('/billing/invoices/view/' . $id);
        }
        $status = $date < today() ? $inv['status'] : 'pending';
        db()->update('invoices', ['due_date' => $date, 'status' => $status, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        audit('invoice.due_date_changed', 'invoice', $id, ['from' => $inv['due_date'], 'to' => $date]);
        flash('success', 'Due date updated.');
        redirect('/billing/invoices/view/' . $id);
    }
}
