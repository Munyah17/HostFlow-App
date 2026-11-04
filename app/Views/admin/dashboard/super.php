<?= view('components.heading', ['title' => 'Business overview', 'subtitle' => setting('business_name') . ' · ' . date('l, d F Y')], null) ?>

<div class="stats">
  <div class="stat"><span>Monthly recurring revenue</span><strong><?= money($mrr) ?></strong><small><?= (int) $c['web_active'] ?> web · <?= (int) $c['email_active'] ?> email active</small></div>
  <div class="stat"><span>Collected this month</span><strong><?= money($c['revenue_month']) ?></strong><small><?= (int) $c['payments_month'] ?> payment(s)</small></div>
  <div class="stat"><a href="<?= url('/billing/invoices', ['status' => 'pending']) ?>"><span>Outstanding invoices</span><strong><?= money($c['pending_amount'] + $c['overdue_amount']) ?></strong><small><?= (int) ($c['invoices_pending'] + $c['invoices_overdue']) ?> unpaid</small></a></div>
  <div class="stat"><a href="<?= url('/billing/invoices', ['status' => 'overdue']) ?>"><span>Overdue</span><strong><?= money($c['overdue_amount']) ?></strong><small><?= (int) $c['invoices_overdue'] ?> invoice(s)</small></a></div>
  <div class="stat"><a href="<?= url('/customers') ?>"><span>Total customers</span><strong><?= (int) $c['customers'] ?></strong><small>+<?= (int) $c['new_customers_month'] ?> this month</small></a></div>
  <div class="stat"><a href="<?= url('/hosting', ['status' => 'active']) ?>"><span>Active hosting accounts</span><strong><?= (int) $c['web_active'] ?></strong><small>Web hosting</small></a></div>
  <div class="stat"><a href="<?= url('/email-hosting', ['status' => 'active']) ?>"><span>Active email hosting</span><strong><?= (int) $c['email_active'] ?></strong><small>Email services</small></a></div>
  <div class="stat"><a href="<?= url('/hosting', ['status' => 'suspended']) ?>"><span>Suspended accounts</span><strong><?= (int) $c['suspended'] ?></strong><small><?= (int) $c['termination_flags'] ?> flagged for review</small></a></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Revenue collected · last 12 months</h3><a class="btn small ghost" href="<?= url('/reports') ?>">Reports</a></div>
    <?= view('components.bar_chart', ['series' => $revenue, 'ariaLabel' => 'Revenue collected per month'], null) ?>
  </div>
  <div class="card">
    <div class="card-head"><h3>System status</h3></div>
    <div class="events">
      <div><b>Automation <?= $lastRun ? badge($lastRun['status']) : badge('failed', 'Never run') ?></b><small><?= $lastRun ? 'Last run ' . datetime_fmt($lastRun['started_at']) : 'Configure the cPanel cron job (see README)' ?><?php if ($lastRun && strtotime($lastRun['started_at']) < time() - 3600): ?> · <span style="color:#c54455">cron may not be running</span><?php endif; ?></small></div>
      <div><b>Automation failures (7 days) <?= $c['automation_failures'] ? badge('failed', (string) $c['automation_failures']) : badge('active', '0') ?></b><small><a href="<?= url('/automation', ['status' => 'failed']) ?>">View automation log</a></small></div>
      <?php foreach ($providers as $p): ?>
        <div><b><?= e($p['name']) ?> <?= $p['last_check_ok'] === null ? badge('pending', 'Not tested') : ($p['last_check_ok'] ? badge('active', 'Connected') : badge('failed', 'Error')) ?></b><small><?= e(str_limit($p['last_check_message'] ?? 'Hosting provider', 90)) ?></small></div>
      <?php endforeach; ?>
      <?php if (!$providers): ?><div><b>Hosting provider <?= badge('pending', 'Not configured') ?></b><small><a href="<?= url('/integrations/providers/create') ?>">Add your WHM server</a></small></div><?php endif; ?>
      <div><b>Email notifications <?= $mailEnabled ? badge('active', 'Enabled') : badge('pending', 'Not configured') ?></b><small><a href="<?= url('/settings/email') ?>">SMTP settings</a><?php if ($c['notifications_failed']): ?> · <?= (int) $c['notifications_failed'] ?> failed message(s)<?php endif; ?></small></div>
      <div><b>Paynow <?= $paynowEnabled ? badge('active', 'Enabled') : badge('pending', 'Not configured') ?></b><small><a href="<?= url('/settings/payments') ?>">Payment settings</a></small></div>
    </div>
  </div>
</div>

<div class="columns">
  <?= view('admin.dashboard._attention', ['attention' => $attention, 'c' => $c], null) ?>
  <div class="card">
    <div class="card-head"><h3>Open support tickets</h3><a class="btn small ghost" href="<?= url('/support') ?>">All tickets</a></div>
    <?php if (!$tickets): ?><div class="empty">No open tickets.</div><?php else: ?>
    <div class="events">
      <?php foreach ($tickets as $t): ?><div><b><a href="<?= url('/support/tickets/' . $t['id']) ?>"><?= e($t['number']) ?> · <?= e(str_limit($t['subject'], 50)) ?></a> <?= badge($t['priority']) ?></b><small><?= e($t['full_name']) ?> · <?= status_label($t['status']) ?> · <?= datetime_fmt($t['updated_at']) ?></small></div><?php endforeach; ?>
    </div><?php endif; ?>
  </div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Recent payments</h3><a class="btn small ghost" href="<?= url('/payments') ?>">All payments</a></div>
    <?= view('admin.payments._table', ['payments' => $recentPayments, 'compact' => true], null) ?>
  </div>
  <div class="card">
    <div class="card-head"><h3>Recent customers</h3><a class="btn small ghost" href="<?= url('/customers') ?>">All customers</a></div>
    <?php if (!$recentCustomers): ?><div class="empty">No customers yet. <a href="<?= url('/customers/create') ?>">Add the first customer</a>.</div><?php else: ?>
    <div class="events">
      <?php foreach ($recentCustomers as $cu): ?><div><b><a href="<?= url('/customers/view/' . $cu['id']) ?>"><?= e($cu['business_name'] ?: $cu['full_name']) ?></a></b><small><?= e($cu['email']) ?> · joined <?= date_fmt($cu['created_at']) ?></small></div><?php endforeach; ?>
    </div><?php endif; ?>
  </div>
</div>

<?php if ($failures): ?>
<div class="card">
  <div class="card-head"><h3>Latest automation failures</h3><a class="btn small ghost" href="<?= url('/automation', ['status' => 'failed']) ?>">View all</a></div>
  <div class="table-scroll"><table><thead><tr><th>Time</th><th>Task</th><th>Action</th><th>Message</th></tr></thead><tbody>
  <?php foreach ($failures as $f): ?><tr><td><?= datetime_fmt($f['created_at']) ?></td><td><?= e($f['task']) ?></td><td><?= e($f['action']) ?></td><td class="wrap"><?= e($f['message']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
