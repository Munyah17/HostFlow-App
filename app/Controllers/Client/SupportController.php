<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Admin\SupportController as AdminSupport;
use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Services\Notifications\NotificationService;

final class SupportController extends Controller
{
    public function index(): string
    {
        $tickets = db()->fetchAll('SELECT * FROM support_tickets WHERE customer_id = ? ORDER BY updated_at DESC LIMIT 100', [Auth::customerId()]);
        return $this->view('client.support.index', ['title' => 'Support', 'tickets' => $tickets]);
    }

    public function create(): string
    {
        return $this->view('client.support.form', [
            'title' => 'New support ticket',
            'services' => db()->fetchAll("SELECT id, domain FROM services WHERE customer_id = ? AND status NOT IN ('terminated','cancelled') ORDER BY domain", [Auth::customerId()]),
            'categories' => ticket_categories(),
        ]);
    }

    public function store(): never
    {
        $data = $this->validate([
            'service_id' => 'integer|exists:services,id',
            'subject' => 'required|max:190',
            'category' => 'required|max:60',
            'priority' => 'required|in:low,normal,high,urgent',
            'message' => 'required|max:20000',
        ]);
        if ($data['service_id'] && !db()->fetchColumn('SELECT COUNT(*) FROM services WHERE id = ? AND customer_id = ?', [(int) $data['service_id'], Auth::customerId()])) {
            $data['service_id'] = '';
        }
        $id = AdminSupport::createTicket(
            Auth::customerId(),
            $data['service_id'] ? (int) $data['service_id'] : null,
            $data['subject'],
            $data['category'],
            $data['priority'],
            $data['message'],
            Auth::id(),
            'client'
        );
        $ticket = db()->fetch('SELECT * FROM support_tickets WHERE id = ?', [$id]);
        NotificationService::alertStaff('New ticket ' . $ticket['number'] . ': ' . $ticket['subject'], $data['message']);
        audit('ticket.created', 'ticket', $id, ['via' => 'client_portal']);
        flash('success', 'Ticket ' . $ticket['number'] . ' opened. We will reply by email and here.');
        redirect('/client/support/' . $id);
    }

    public function show(int $id): string
    {
        $t = $this->ticket($id);
        return $this->view('client.support.show', [
            'title' => $t['number'] . ' · ' . $t['subject'],
            't' => $t,
            'messages' => db()->fetchAll('SELECT m.*, u.name, u.role FROM support_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ? AND m.is_internal = 0 ORDER BY m.id', [$id]),
        ]);
    }

    public function reply(int $id): never
    {
        $t = $this->ticket($id);
        $message = trim((string) Request::input('message', ''));
        if ($message === '' || mb_strlen($message) > 20000) {
            back_with_errors(['message' => 'Enter a reply.'], '/client/support/' . $id);
        }
        if ($t['status'] === 'closed') {
            flash('error', 'This ticket is closed. Open a new ticket if you still need help.');
            redirect('/client/support');
        }
        db()->transaction(function ($db) use ($id, $message) {
            $db->insert('support_messages', ['ticket_id' => $id, 'user_id' => Auth::id(), 'message' => $message, 'is_internal' => 0, 'created_at' => now()]);
            $db->update('support_tickets', ['status' => 'open', 'last_reply_at' => now(), 'last_reply_by' => 'client', 'updated_at' => now()], 'id = :id', ['id' => $id]);
        });
        NotificationService::alertStaff('Customer reply on ' . $t['number'] . ': ' . $t['subject'], $message);
        flash('success', 'Reply sent.');
        redirect('/client/support/' . $id);
    }

    private function ticket(int $id): array
    {
        $t = db()->fetch('SELECT t.*, s.domain FROM support_tickets t LEFT JOIN services s ON s.id = t.service_id WHERE t.id = ? AND t.customer_id = ?', [$id, Auth::customerId()]);
        if (!$t) {
            abort(404);
        }
        return $t;
    }
}
