<?= view('components.heading', [
    'title' => 'Activity log',
    'subtitle' => 'Every staff action is recorded here',
], null) ?>

<div class="card">
  <form class="filters" method="get" action="<?= url('/activity-log') ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search action, user or details…">
    <select name="entity" data-autosubmit>
      <option value="">All entities</option>
      <?php foreach (['customer', 'service', 'invoice', 'ticket', 'plan', 'provider', 'user', 'plugin', 'system', 'cron_run'] as $en): ?>
        <option value="<?= $en ?>" <?= $entity === $en ? 'selected' : '' ?>><?= e(ucfirst($en)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn ghost" type="submit">Filter</button>
  </form>

  <?php if (!$logs): ?><div class="empty">No activity recorded.</div><?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><small><?= datetime_fmt($l['created_at']) ?></small></td>
        <td><?= e($l['user_name'] ?: 'System') ?></td>
        <td><span class="mono"><?= e($l['action']) ?></span></td>
        <td><small><?= $l['entity_type'] ? e($l['entity_type']) . ($l['entity_id'] ? ' #' . (int) $l['entity_id'] : '') : '—' ?></small></td>
        <td class="wrap"><small class="muted"><?= e(str_limit($l['details'] ?? '', 140)) ?></small></td>
        <td><small class="muted mono"><?= e($l['ip'] ?: '') ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= view('components.pagination', ['p' => $p], null) ?>
  <?php endif; ?>
</div>
