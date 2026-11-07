<?= view('components.heading', ['title' => 'Operations overview', 'subtitle' => setting('business_name') . ' · ' . date('l, d F Y')], null) ?>

<div class="stats">
  <div class="stat"><a href="<?= url('/support', ['status' => 'open']) ?>"><span>Open support tickets</span><strong><?= (int) $c['tickets_open'] ?></strong><small><?= (int) $c['tickets_awaiting'] ?> awaiting reply</small></a></div>
  <div class="stat"><a href="<?= url('/customers') ?>"><span>New customers</span><strong><?= (int) $c['new_customers_30'] ?></strong><small>Last 30 days</small></a></div>
  <div class="stat"><a href="<?= url('/billing/invoices', ['status' => 'pending']) ?>"><span>Pending invoices</span><strong><?= (int) $c['invoices_pending'] ?></strong><small><?= money($c['pending_amount']) ?></small></a></div>
  <div class="stat"><a href="<?= url('/billing/invoices', ['status' => 'overdue']) ?>"><span>Overdue accounts</span><strong><?= (int) $c['overdue_customers'] ?></strong><small><?= money($c['overdue_amount']) ?> overdue</small></a></div>
  <div class="stat"><a href="<?= url('/hosting', ['status' => 'suspended']) ?>"><span>Suspended services</span><strong><?= (int) $c['suspended'] ?></strong><small>Web and email</small></a></div>
  <div class="stat"><a href="<?= url('/hosting', ['status' => 'provisioning_failed']) ?>"><span>Provisioning failures</span><strong><?= (int) $c['provisioning_failed'] ?></strong><small><?= (int) $c['provisioning'] ?> awaiting provisioning</small></a></div>
  <div class="stat"><span>Collected this month</span><strong><?= money($c['revenue_month']) ?></strong><small><?= (int) $c['payments_month'] ?> payment(s)</small></div>
  <div class="stat"><span>Automation</span><strong class="stat-badge"><?= $lastRun ? badge($lastRun['status']) : badge('failed', 'Never run') ?></strong><small><?= $lastRun ? 'Last run ' . datetime_fmt($lastRun['started_at']) : 'Cron not configured' ?></small></div>
</div>

<div class="columns">
  <?= view('admin.dashboard._attention', ['attention' => $attention, 'c' => $c], null) ?>
  <div class="card">
    <div class="card-head"><h3>Open support tickets</h3><a class="btn small ghost" href="<?= url('/support') ?>">All tickets</a></div>
    <?php if (!$tickets): ?><div class="empty">No open tickets.</div><?php else: ?>
    <div class="events">
      <?php foreach ($tickets as $t): ?><div><b><a href="<?= url('/support/tickets/' . $t['id']) ?>"><?= e($t['number']) ?> · <?= e(str_limit($t['subject'], 50)) ?></a> <?= badge($t['priority']) ?></b><small><?= e($t['full_name']) ?> · <?= status_label($t['status']) ?></small></div><?php endforeach; ?>
    </div><?php endif; ?>
  </div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Unpaid invoices</h3><a class="btn small ghost" href="<?= url('/billing/invoices') ?>">All invoices</a></div>
    <?php if (!$pendingInvoices): ?><div class="empty">No unpaid invoices.</div><?php else: ?>
    <div class="table-scroll"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Balance</th><th>Due</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($pendingInvoices as $i): ?><tr><td><a href="<?= url('/billing/invoices/view/' . $i['id']) ?>"><?= e($i['number']) ?></a></td><td><?= e($i['full_name']) ?></td><td><?= money($i['total'] - $i['amount_paid']) ?></td><td><?= date_fmt($i['due_date']) ?></td><td><?= badge($i['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3>Recent payments</h3><a class="btn small ghost" href="<?= url('/payments') ?>">All payments</a></div>
    <?= view('admin.payments._table', ['payments' => $recentPayments, 'compact' => true], null) ?>
  </div>
</div>
