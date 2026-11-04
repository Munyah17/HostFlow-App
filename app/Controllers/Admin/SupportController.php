<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Services\Notifications\NotificationService;

final class SupportController extends Controller
{
    public function index(): string
    {
        $q = (string) Request::query('q', '');
        $status = (string) Request::query('status', '');
        $priority = (string) Request::query('priority', '');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(t.number LIKE ? OR t.subject LIKE ? OR c.full_name LIKE ? OR c.business_name LIKE ?)';
            array_push($params, ...array_fill(0, 4, '%' . $q . '%'));
        }
        if ($status === 'awaiting') {
            $where[] = "t.status = 'open' AND t.last_reply_by = 'client'";
        } elseif (in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if (in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $where[] = 't.priority = ?';
            $params[] = $priority;
        }
        $base = 'SELECT t.*, c.full_name, c.business_name, u.name AS assignee
                 FROM support_tickets t JOIN customers c ON c.id = t.customer_id LEFT JOIN users u ON u.id = t.assigned_to
                 WHERE ' . implode(' AND ', $where);
        $total = (int) db()->fetchColumn("SELECT COUNT(*) FROM ({$base}) t", $params);
        $p = paginate($total, 25);
        $tickets = db()->fetchAll($base . " ORDER BY FIELD(t.status,'open','pending','resolved','closed'), FIELD(t.priority,'urgent','high','normal','low'), t.updated_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);
        $stats = db()->fetch("SELECT SUM(status = 'open') AS open, SUM(status = 'open' AND last_reply_by = 'client') AS awaiting, SUM(status = 'pending') AS pending, SUM(priority IN ('high','urgent') AND status IN ('open','pending')) AS urgent FROM support_tickets");
        return $this->view('admin.support.index', compact('tickets', 'p', 'stats', 'q', 'status', 'priority') + ['title' => 'Support tickets']);
    }

    public function create(): string
    {
        $customerId = (int) Request::query('customer_id', 0);
        return $this->view('admin.support.form', [
            'title' => 'Open ticket',
            'customers' => db()->fetchAll('SELECT id, full_name, business_name, email FROM customers ORDER BY business_name, full_name'),
            'services' => $customerId ? db()->fetchAll("SELECT id, domain FROM services WHERE customer_id = ? AND status NOT IN ('terminated','cancelled') ORDER BY domain", [$customerId]) : [],
            'customerId' => $customerId,
            'categories' => ticket_categories(),
        ]);
    }

    /** Open a ticket on a customer's behalf (e.g. from a phone call). */
    public function store(): never
    {
        $data = $this->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'service_id' => 'integer|exists:services,id',
            'subject' => 'required|max:190',
            'category' => 'required|max:60',
            'priority' => 'required|in:low,normal,high,urgent',
            'message' => 'required|max:20000',
        ], ['customer_id' => 'Customer']);
        if ($data['service_id'] && !db()->fetchColumn('SELECT COUNT(*) FROM services WHERE id = ? AND customer_id = ?', [(int) $data['service_id'], (int) $data['customer_id']])) {
            $data['service_id'] = '';
        }
        $id = self::createTicket((int) $data['customer_id'], $data['service_id'] ? (int) $data['service_id'] : null, $data['subject'], $data['category'], $data['priority'], $data['message'], Auth::id(), 'staff');
        audit('ticket.created', 'ticket', $id, ['customer_id' => $data['customer_id']]);
        flash('success', 'Ticket opened.');
        redirect('/support/tickets/' . $id);
    }

    public function show(int $id): string
    {
        $t = $this->ticket($id);
        return $this->view('admin.support.show', [
            'title' => $t['number'] . ' · ' . $t['subject'],
            't' => $t,
            'messages' => db()->fetchAll('SELECT m.*, u.name, u.role FROM support_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ? ORDER BY m.id', [$id]),
            'staff' => db()->fetchAll("SELECT id, name FROM users WHERE role IN ('super_admin','admin') AND status = 'active' ORDER BY name"),
        ]);
    }

    public function reply(int $id): never
    {
        $t = $this->ticket($id);
        $message = trim((string) Request::input('message', ''));
        $internal = !empty($_POST['internal']);
        if ($message === '' || mb_strlen($message) > 20000) {
            back_with_errors(['message' => 'Enter a reply (max 20,000 characters).'], '/support/tickets/' . $id);
        }
        db()->transaction(function ($db) use ($id, $message, $internal, $t) {
            $db->insert('support_messages', ['ticket_id' => $id, 'user_id' => Auth::id(), 'message' => $message, 'is_internal' => $internal ? 1 : 0, 'created_at' => now()]);
            $status = $internal ? $t['status'] : ($t['status'] === 'closed' ? 'open' : ($t['status'] === 'resolved' ? 'resolved' : 'pending'));
            $db->update('support_tickets', [
                'status' => $internal ? $t['status'] : $status,
                'last_reply_at' => now(),
                'last_reply_by' => 'staff',
                'assigned_to' => $t['assigned_to'] ?: Auth::id(),
                'updated_at' => now(),
            ], 'id = :id', ['id' => $id]);
        });
        audit('ticket.replied', 'ticket', $id, ['internal' => $internal]);
        if (!$internal) {
            NotificationService::notifyCustomer((int) $t['customer_id'], 'ticket_reply', [
                'ticket_number' => $t['number'],
                'ticket_subject' => $t['subject'],
                'message' => $message,
                'ticket_url' => absolute_url('/client/support/' . $id),
            ]);
        }
        flash('success', $internal ? 'Internal note added.' : 'Reply sent to the customer.');
        redirect('/support/tickets/' . $id);
    }

    /** Update status / priority / assignee. */
    public function update(int $id): never
    {
        $t = $this->ticket($id);
        $data = $this->validate([
            'status' => 'required|in:open,pending,resolved,closed',
            'priority' => 'required|in:low,normal,high,urgent',
            'assigned_to' => 'integer|exists:users,id',
        ]);
        db()->update('support_tickets', [
            'status' => $data['status'],
            'priority' => $data['priority'],
            'assigned_to' => $data['assigned_to'] !== '' ? (int) $data['assigned_to'] : null,
            'updated_at' => now(),
        ], 'id = :id', ['id' => $id]);
        if ($data['status'] !== $t['status'] || $data['priority'] !== $t['priority']) {
            audit('ticket.updated', 'ticket', $id, ['status' => [$t['status'], $data['status']], 'priority' => [$t['priority'], $data['priority']]]);
        }
        flash('success', 'Ticket updated.');
        redirect('/support/tickets/' . $id);
    }

    /** Shared ticket creation used by admin and (later) the client portal. */
    public static function createTicket(int $customerId, ?int $serviceId, string $subject, string $category, string $priority, string $message, ?int $userId, string $openedBy): int
    {
        return db()->transaction(function ($db) use ($customerId, $serviceId, $subject, $category, $priority, $message, $userId, $openedBy) {
            $id = $db->insert('support_tickets', [
                'number' => 'TMP-' . bin2hex(random_bytes(6)),
                'customer_id' => $customerId,
                'service_id' => $serviceId,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => 'open',
                'last_reply_at' => now(),
                'last_reply_by' => $openedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $db->update('support_tickets', ['number' => 'T-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)], 'id = :id', ['id' => $id]);
            $db->insert('support_messages', ['ticket_id' => $id, 'user_id' => $userId, 'message' => $message, 'is_internal' => 0, 'created_at' => now()]);
            return $id;
        });
    }

    private function ticket(int $id): array
    {
        $t = db()->fetch('SELECT t.*, c.full_name, c.business_name, c.email, s.domain, u.name AS assignee FROM support_tickets t JOIN customers c ON c.id = t.customer_id LEFT JOIN services s ON s.id = t.service_id LEFT JOIN users u ON u.id = t.assigned_to WHERE t.id = ?', [$id]);
        if (!$t) {
            abort(404);
        }
        return $t;
    }
}
