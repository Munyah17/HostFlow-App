<?= view('components.heading', [
    'title' => 'Support tickets',
    'subtitle' => (int) $stats['open'] . ' open · ' . (int) $stats['awaiting'] . ' awaiting a reply',
    'actions' => '<a class="btn primary" href="' . url('/support/create') . '">+ Open ticket</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><a href="<?= url('/support', ['status' => 'awaiting']) ?>"><span>Awaiting reply</span><strong><?= (int) $stats['awaiting'] ?></strong><small>Customer replied last</small></a></div>
  <div class="stat"><a href="<?= url('/support', ['status' => 'open']) ?>"><span>Open</span><strong><?= (int) $stats['open'] ?></strong><small><?= (int) $stats['pending'] ?> pending</small></a></div>
  <div class="stat"><a href="<?= url('/support', ['priority' => 'urgent']) ?>"><span>High / urgent</span><strong><?= (int) $stats['urgent'] ?></strong><small>Unresolved</small></a></div>
</div>

<div class="card">
  <form class="filters" method="get" action="<?= url('/support') ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search ticket, subject or customer…">
    <select name="status" data-autosubmit>
      <option value="">All statuses</option>
      <option value="awaiting" <?= $status === 'awaiting' ? 'selected' : '' ?>>Awaiting reply</option>
      <?php foreach (['open', 'pending', 'resolved', 'closed'] as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option><?php endforeach; ?>
    </select>
    <select name="priority" data-autosubmit>
      <option value="">Any priority</option>
      <?php foreach (['urgent', 'high', 'normal', 'low'] as $pr): ?><option value="<?= $pr ?>" <?= $priority === $pr ? 'selected' : '' ?>><?= e(ucfirst($pr)) ?></option><?php endforeach; ?>
    </select>
    <button class="btn ghost" type="submit">Search</button>
  </form>

  <?php if (!$tickets): ?>
    <div class="empty">No tickets found.<?= $q || $status || $priority ? ' <a href="' . url('/support') . '">Clear filters</a>.' : '' ?></div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr>
        <td><a href="<?= url('/support/tickets/' . $t['id']) ?>"><?= e($t['number']) ?></a></td>
        <td class="wrap"><a href="<?= url('/support/tickets/' . $t['id']) ?>"><?= e(str_limit($t['subject'], 70)) ?></a><br><small class="muted"><?= e($t['category']) ?></small></td>
        <td><a href="<?= url('/customers/view/' . $t['customer_id']) ?>"><?= e($t['business_name'] ?: $t['full_name']) ?></a></td>
        <td><?= badge($t['priority']) ?></td>
        <td><?= badge($t['status']) ?><?= $t['status'] === 'open' && $t['last_reply_by'] === 'client' ? ' ' . badge('urgent', 'reply needed') : '' ?></td>
        <td><small><?= e($t['assignee'] ?: '—') ?></small></td>
        <td><small><?= datetime_fmt($t['updated_at']) ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= view('components.pagination', ['p' => $p], null) ?>
  <?php endif; ?>
</div>
