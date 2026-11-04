<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Services\AutomationService;
use App\Services\Notifications\NotificationService;

final class AutomationController extends Controller
{
    public function index(): string
    {
        $task = (string) Request::query('task', '');
        $status = (string) Request::query('status', '');
        $where = ['1=1'];
        $params = [];
        if ($task !== '' && isset(AutomationService::TASKS[$task])) {
            $where[] = 'l.task = ?';
            $params[] = $task;
        }
        if (in_array($status, ['success', 'failed', 'skipped', 'info'], true)) {
            $where[] = 'l.status = ?';
            $params[] = $status;
        }
        $base = 'SELECT l.* FROM automation_logs l WHERE ' . implode(' AND ', $where);
        $total = (int) db()->fetchColumn("SELECT COUNT(*) FROM ({$base}) t", $params);
        $p = paginate($total, 30);
        $logs = db()->fetchAll($base . " ORDER BY l.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);
        return $this->view('admin.automation.index', [
            'title' => 'Automation',
            'logs' => $logs,
            'p' => $p,
            'task' => $task,
            'status' => $status,
            'tasks' => AutomationService::TASKS,
            'runs' => db()->fetchAll('SELECT * FROM cron_runs ORDER BY id DESC LIMIT 10'),
            'notices' => db()->fetch("SELECT SUM(status = 'queued') AS queued, SUM(status = 'failed') AS failed FROM notifications"),
            'failedNotices' => db()->fetchAll("SELECT * FROM notifications WHERE status = 'failed' ORDER BY id DESC LIMIT 20"),
        ]);
    }

    /** Detail of a single automation run. */
    public function run(int $id): string
    {
        $run = db()->fetch('SELECT * FROM cron_runs WHERE id = ?', [$id]);
        if (!$run) {
            abort(404);
        }
        return $this->view('admin.automation.run', [
            'title' => 'Automation run #' . $id,
            'run' => $run,
            'summary' => json_decode((string) $run['summary'], true) ?: [],
            'tasks' => AutomationService::TASKS,
            'logs' => db()->fetchAll('SELECT * FROM automation_logs WHERE run_id = ? ORDER BY id', [$id]),
        ]);
    }

    /** Run automation now (Super Admin). Optionally a subset of tasks. */
    public function runNow(): never
    {
        $only = array_values(array_intersect((array) ($_POST['tasks'] ?? []), array_keys(AutomationService::TASKS)));
        $result = AutomationService::run($only ?: null);
        if ($result === null) {
            flash('error', 'An automation run is already in progress.');
        } else {
            audit('automation.manual_run', 'cron_run', $result['run_id'], $only ? ['tasks' => $only] : null);
            flash($result['status'] === 'completed' ? 'success' : 'warning', 'Run #' . $result['run_id'] . ' finished: ' . status_label($result['status']) . '.');
            redirect('/automation/runs/' . $result['run_id']);
        }
        redirect('/automation');
    }

    /** Re-queue a failed notification. */
    public function retryNotification(int $id): never
    {
        try {
            NotificationService::retry($id);
            flash('success', 'Notification re-queued.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/automation');
    }
}
