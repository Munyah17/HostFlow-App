<?= view('components.heading', [
    'title' => 'Welcome, ' . explode(' ', $customer['full_name'])[0],
    'subtitle' => setting('business_name') . ' · ' . date('l, d F Y'),
    'actions' => '<a class="btn primary" href="' . url('/client/order') . '">+ Order hosting</a>',
], null) ?>

<div class="stats">
  <div class="stat"><a href="<?= url('/client/services') ?>"><span>Active services</span><strong><?= (int) $stats['active'] ?></strong><small><?= (int) $stats['suspended'] ?> suspended</small></a></div>
  <div class="stat"><a href="<?= url('/client/invoices') ?>"><span>Balance due</span><strong><?= money($stats['balance']) ?></strong><small><?= (int) $stats['unpaid'] ?> unpaid invoice(s)</small></a></div>
  <div class="stat"><a href="<?= url('/client/support') ?>"><span>Open tickets</span><strong><?= (int) $stats['tickets'] ?></strong><small>Support</small></a></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Your services</h3><a class="btn small ghost" href="<?= url('/client/services') ?>">All services</a></div>
    <?php if (!$services): ?><div class="empty">No services yet. <a href="<?= url('/client/order') ?>">Order hosting</a> to get started.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Domain</th><th>Plan</th><th>Next due</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($services as $s): ?>
        <tr>
          <td><a href="<?= url('/client/services/' . $s['id']) ?>"><?= e($s['domain']) ?></a></td>
          <td><?= e($s['plan_name']) ?></td>
          <td><?= date_fmt($s['next_due_date']) ?></td>
          <td><?= badge($s['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3>Recent invoices</h3><a class="btn small ghost" href="<?= url('/client/invoices') ?>">All invoices</a></div>
    <?php if (!$invoices): ?><div class="empty">No invoices.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Invoice</th><th>Due</th><th class="text-right">Total</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($invoices as $i): ?>
        <tr>
          <td><a href="<?= url('/client/invoices/' . $i['id']) ?>"><?= e($i['number']) ?></a></td>
          <td><?= date_fmt($i['due_date']) ?></td>
          <td class="text-right"><?= money($i['total'], $i['currency']) ?></td>
          <td><?= badge($i['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>

    <h4>Open tickets</h4>
    <?php if (!$tickets): ?><div class="empty">No open tickets. <a href="<?= url('/client/support/create') ?>">Get help</a>.</div><?php else: ?>
    <div class="events">
      <?php foreach ($tickets as $t): ?><div><b><a href="<?= url('/client/support/' . $t['id']) ?>"><?= e($t['number']) ?> · <?= e(str_limit($t['subject'], 50)) ?></a></b><small><?= status_label($t['status']) ?> · updated <?= datetime_fmt($t['updated_at']) ?></small></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
