<?= view('components.heading', [
    'title' => 'Reports',
    'subtitle' => 'Business metrics · ' . setting('business_name'),
], null) ?>

<div class="stats">
  <div class="stat"><span>Monthly recurring revenue</span><strong><?= money($mrr['total']) ?></strong><small><?= money($mrr['web']) ?> web · <?= money($mrr['email']) ?> email</small></div>
  <div class="stat"><span>Collected this month</span><strong><?= money($c['revenue_month']) ?></strong><small><?= (int) $c['payments_month'] ?> payment(s)</small></div>
  <div class="stat"><span>Expenses this month</span><strong><?= money($expenses['month']) ?></strong><small><?= money($expenses['recurring']) ?> recurring · <a href="<?= url('/expenses') ?>">ledger</a></small></div>
  <div class="stat"><span>Net this month</span><strong><?= money($c['revenue_month'] - $expenses['month']) ?></strong><small>Collected minus expenses</small></div>
  <div class="stat"><span>Active services</span><strong><?= (int) $c['web_active'] + (int) $c['email_active'] ?></strong><small><?= (int) $c['web_active'] ?> web · <?= (int) $c['email_active'] ?> email</small></div>
  <div class="stat"><span>Customers</span><strong><?= (int) $c['customers'] ?></strong><small>+<?= (int) $c['new_customers_month'] ?> this month</small></div>
  <div class="stat"><span>Overdue balances</span><strong><?= money($c['overdue_amount']) ?></strong><small><?= (int) $c['overdue_customers'] ?> customer(s)</small></div>
  <div class="stat"><span>Churn risk</span><strong><?= (int) $c['suspended'] ?></strong><small>Suspended service(s)</small></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Revenue collected · last 12 months</h3></div>
    <?= view('components.bar_chart', ['series' => $revenue, 'ariaLabel' => 'Revenue collected per month'], null) ?>
  </div>
  <div class="card">
    <div class="card-head"><h3>New customers · last 12 months</h3></div>
    <?= view('components.bar_chart', ['series' => $newCustomers, 'ariaLabel' => 'New customers per month'], null) ?>
  </div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>MRR by plan</h3></div>
    <div class="table-scroll"><table>
      <thead><tr><th>Plan</th><th>Type</th><th class="text-right">Active services</th><th class="text-right">MRR</th></tr></thead>
      <tbody>
      <?php foreach ($byProduct as $p): ?>
        <tr><td><?= e($p['name']) ?></td><td><?= $p['type'] === 'email' ? 'Email' : 'Web' ?></td><td class="text-right"><?= (int) $p['active_services'] ?></td><td class="text-right"><?= money($p['mrr']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <div class="card-head"><h3>Overdue balances</h3></div>
    <?php if (!$overdue): ?><div class="empty">No overdue balances.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Customer</th><th class="text-right">Overdue invoices</th><th class="text-right">Balance</th><th>Oldest due</th></tr></thead>
      <tbody>
      <?php foreach ($overdue as $o): ?>
        <tr><td><a href="<?= url('/customers/view/' . $o['id']) ?>"><?= e($o['business_name'] ?: $o['full_name']) ?></a></td><td class="text-right"><?= (int) $o['invoices'] ?></td><td class="text-right"><?= money($o['balance']) ?></td><td><?= date_fmt($o['oldest_due']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3>Upcoming renewals</h3>
    <form class="filters flush" method="get" action="<?= url('/reports') ?>">
      <select name="days" data-autosubmit>
        <?php foreach ([7, 14, 30, 60, 90] as $d): ?><option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>>Next <?= $d ?> days</option><?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php if (!$renewals): ?><div class="empty">No renewals due in the next <?= (int) $days ?> days.</div><?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Domain</th><th>Customer</th><th>Plan</th><th class="text-right">Price</th><th>Due</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($renewals as $r): ?>
      <tr>
        <td><a href="<?= url('/hosting/view/' . $r['id']) ?>"><?= e($r['domain']) ?></a></td>
        <td><a href="<?= url('/customers/view/' . $r['customer_id']) ?>"><?= e($r['business_name'] ?: $r['full_name']) ?></a></td>
        <td><?= e($r['plan_name']) ?> <small class="muted">/ <?= e(cycle_label($r['billing_cycle'])) ?></small></td>
        <td class="text-right"><?= money($r['price']) ?></td>
        <td><?= date_fmt($r['next_due_date']) ?><?= $r['next_due_date'] <= today() ? ' ' . badge('overdue', 'due') : '' ?></td>
        <td><?= badge($r['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
