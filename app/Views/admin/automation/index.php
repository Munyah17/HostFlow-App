<?php
use App\Core\Auth;

$lastRun = $runs[0] ?? null;
$actions = Auth::isSuper()
    ? '<form method="post" action="' . url('/automation/run') . '" class="inline" data-confirm="Run all automation tasks now?" data-confirm-style="primary">' . csrf_field() . '<button class="btn primary">Run now</button></form>'
    : '';
?>
<?= view('components.heading', [
    'title' => 'Automation',
    'subtitle' => $lastRun ? 'Last run ' . datetime_fmt($lastRun['started_at']) . ' · ' . status_label($lastRun['status']) : 'Never run — set up the cron job (cron/run.php every 5 minutes)',
    'actions' => $actions,
], null) ?>

<div class="stats three">
  <div class="stat"><span>Notification queue</span><strong><?= (int) $notices['queued'] ?></strong><small>Queued emails</small></div>
  <div class="stat"><span>Failed notifications</span><strong><?= (int) $notices['failed'] ?></strong><small>Need retry or attention</small></div>
  <div class="stat"><span>Last run</span><strong style="font-size:18px"><?= $lastRun ? badge($lastRun['status']) : badge('muted', '—') ?></strong><small><?= $lastRun ? datetime_fmt($lastRun['finished_at'] ?? $lastRun['started_at']) : 'Cron not configured' ?></small></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Automation log</h3></div>
    <form class="filters" method="get" action="<?= url('/automation') ?>">
      <select name="task" data-autosubmit>
        <option value="">All tasks</option>
        <?php foreach ($tasks as $key => $label): ?><option value="<?= $key ?>" <?= $task === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <select name="status" data-autosubmit>
        <option value="">All results</option>
        <?php foreach (['success', 'failed', 'skipped', 'info'] as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option><?php endforeach; ?>
      </select>
      <button class="btn ghost" type="submit">Filter</button>
    </form>
    <?php if (!$logs): ?><div class="empty">No log entries.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Time</th><th>Task</th><th>Action</th><th>Message</th><th>Result</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><small><?= datetime_fmt($l['created_at']) ?></small></td>
          <td><?= e($tasks[$l['task']] ?? $l['task']) ?></td>
          <td><?= e($l['action']) ?><?php if ($l['entity_type']): ?><br><small class="muted"><?= e($l['entity_type']) ?> #<?= (int) $l['entity_id'] ?></small><?php endif; ?></td>
          <td class="wrap"><small><?= e(str_limit($l['message'], 140)) ?></small></td>
          <td><?= badge($l['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?= view('components.pagination', ['p' => $p], null) ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Recent runs</h3></div>
    <?php if (!$runs): ?><div class="empty">No runs yet.</div><?php else: ?>
    <div class="events">
      <?php foreach ($runs as $r): ?><div><b><a href="<?= url('/automation/runs/' . $r['id']) ?>">Run #<?= (int) $r['id'] ?></a> <?= badge($r['status']) ?></b><small><?= datetime_fmt($r['started_at']) ?> · <?= $r['finished_at'] ? 'finished ' . datetime_fmt($r['finished_at']) : 'still running' ?></small></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h4>Tasks</h4>
    <div class="events">
      <?php foreach ($tasks as $label): ?><div><b><?= e($label) ?></b></div><?php endforeach; ?>
    </div>
  </div>
</div>

<?php if ($failedNotices): ?>
<div class="card">
  <div class="card-head"><h3>Failed notifications</h3></div>
  <div class="table-scroll"><table>
    <thead><tr><th>To</th><th>Template</th><th>Error</th><th>Attempts</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($failedNotices as $n): ?>
      <tr>
        <td><?= e($n['recipient']) ?><br><small class="muted"><?= e($n['subject']) ?></small></td>
        <td><?= e($n['template']) ?></td>
        <td class="wrap"><small><?= e(str_limit($n['last_error'], 120)) ?></small></td>
        <td><?= (int) $n['attempts'] ?></td>
        <td class="text-right"><form method="post" action="<?= url('/automation/notifications/' . $n['id'] . '/retry') ?>" class="inline"><?= csrf_field() ?><button class="btn small ghost">Retry</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
