<?= view('components.heading', [
    'title' => 'Support',
    'subtitle' => 'Get help with your services',
    'actions' => '<a class="btn primary" href="' . url('/client/support/create') . '">+ New ticket</a>',
], null) ?>

<div class="card">
  <?php if (!$tickets): ?>
    <div class="empty">No tickets yet. <a href="<?= url('/client/support/create') ?>">Open one</a> and we'll get back to you.</div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Ticket</th><th>Subject</th><th>Priority</th><th>Status</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr>
        <td><a href="<?= url('/client/support/' . $t['id']) ?>"><?= e($t['number']) ?></a></td>
        <td class="wrap"><a href="<?= url('/client/support/' . $t['id']) ?>"><?= e(str_limit($t['subject'], 70)) ?></a><br><small class="muted"><?= e($t['category']) ?></small></td>
        <td><?= badge($t['priority']) ?></td>
        <td><?= badge($t['status']) ?></td>
        <td><small><?= datetime_fmt($t['updated_at']) ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
