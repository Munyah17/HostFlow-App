<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;

final class AuditController extends Controller
{
    public function index(): string
    {
        $q = (string) Request::query('q', '');
        $entity = (string) Request::query('entity', '');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(a.action LIKE ? OR u.name LIKE ? OR a.details LIKE ?)';
            array_push($params, ...array_fill(0, 3, '%' . $q . '%'));
        }
        if (in_array($entity, ['customer', 'service', 'invoice', 'ticket', 'plan', 'provider', 'user', 'system', 'cron_run'], true)) {
            $where[] = 'a.entity_type = ?';
            $params[] = $entity;
        }
        $base = 'SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where);
        $total = (int) db()->fetchColumn("SELECT COUNT(*) FROM ({$base}) t", $params);
        $p = paginate($total, 40);
        return $this->view('admin.audit.index', [
            'title' => 'Activity log',
            'logs' => db()->fetchAll($base . " ORDER BY a.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params),
            'p' => $p,
            'q' => $q,
            'entity' => $entity,
        ]);
    }
}
