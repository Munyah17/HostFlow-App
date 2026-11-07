<?= view('components.heading', [
    'title' => 'Automation run #' . $run['id'],
    'subtitle' => 'Started ' . datetime_fmt($run['started_at']) . ($run['finished_at'] ? ' · finished ' . datetime_fmt($run['finished_at']) : ' · still running'),
    'actions' => '<a class="btn ghost" href="' . url('/automation') . '">All runs</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><span>Status</span><strong class="stat-badge"><?= badge($run['status']) ?></strong><small><?= (int) count($logs) ?> log entries</small></div>
  <div class="stat"><span>Started</span><strong class="stat-badge"><?= e(date('H:i:s', strtotime($run['started_at']))) ?></strong><small><?= date_fmt($run['started_at']) ?></small></div>
  <div class="stat"><span>Duration</span><strong class="stat-badge"><?= $run['finished_at'] ? e(round(strtotime($run['finished_at']) - strtotime($run['started_at'])) . 's') : '—' ?></strong><small><?= $run['finished_at'] ? '' : 'running' ?></small></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Task summary</h3></div>
    <?php if (!$summary): ?><div class="empty">No summary recorded.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Task</th><th>Result</th></tr></thead>
      <tbody>
      <?php foreach ($summary as $key => $value): ?>
        <tr><td><?= e($tasks[$key] ?? $key) ?></td><td class="wrap"><?= is_scalar($value) ? e((string) $value) : e(json_encode($value)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3>Log entries</h3></div>
    <?php if (!$logs): ?><div class="empty">Nothing was logged for this run.</div><?php else: ?>
    <div class="events">
      <?php foreach ($logs as $l): ?><div><b><?= e($l['task']) ?> · <?= e($l['action']) ?> <?= badge($l['status']) ?></b><small><?= e(str_limit($l['message'], 140)) ?></small></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
