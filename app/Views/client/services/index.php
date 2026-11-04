<?= view('components.heading', [
    'title' => $title,
    'subtitle' => count($services) . ' ' . ($type === 'email' ? 'email' : 'web hosting') . ' service(s)',
    'actions' => '<a class="btn primary" href="' . url('/client/order') . '">+ Order hosting</a>',
], null) ?>

<div class="card">
  <?php if (!$services): ?>
    <div class="empty">No <?= $type === 'email' ? 'email hosting' : 'services' ?> yet. <a href="<?= url('/client/order') ?>">Order hosting</a>.</div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Domain</th><th>Plan</th><th class="text-right">Price</th><th>Next due</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($services as $s): ?>
      <tr>
        <td><a href="<?= url('/client/services/' . $s['id']) ?>"><?= e($s['domain']) ?></a></td>
        <td><?= e($s['plan_name']) ?></td>
        <td class="text-right"><?= money($s['price']) ?> <small class="muted">/ <?= e(cycle_label($s['billing_cycle'])) ?></small></td>
        <td><?= date_fmt($s['next_due_date']) ?></td>
        <td><?= badge($s['status']) ?></td>
        <td class="text-right"><a class="btn small ghost" href="<?= url('/client/services/' . $s['id']) ?>">Manage</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
