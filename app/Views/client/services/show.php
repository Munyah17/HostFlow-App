<?php
$active = $s['status'] === 'active';
$actions = '';
if ($active) {
    $actions = '<form method="post" action="' . url('/client/services/' . $s['id'] . '/login') . '" class="inline" target="_blank">' . csrf_field() . '<button class="btn primary">' . ($s['type'] === 'email' ? 'Open webmail' : 'Open control panel') . '</button></form>';
}
$actions .= '<a class="btn ghost" href="' . url($s['type'] === 'email' ? '/client/email' : '/client/services') . '">All services</a>';
?>
<?= view('components.heading', [
    'title' => $s['domain'],
    'subtitle' => $s['plan_name'] . ' · ' . ($s['type'] === 'email' ? 'Email hosting' : 'Web hosting'),
    'actions' => $actions,
], null) ?>

<?php if ($s['status'] === 'suspended'): ?>
  <div class="alert warning"><b>This service is suspended.</b> <?= e($s['suspension_reason'] ?: 'Contact support for details.') ?> <?php if ($s['suspension_type'] === 'billing'): ?>Pay your outstanding invoice to reactivate automatically.<?php endif; ?></div>
<?php elseif ($s['status'] === 'pending_payment'): ?>
  <div class="alert info"><b>Awaiting payment.</b> Pay the order invoice to activate this service.</div>
<?php elseif ($s['status'] === 'provisioning' || $s['status'] === 'provisioning_failed'): ?>
  <div class="alert info"><b>Being set up.</b> Your account is being provisioned — this usually takes a few minutes.</div>
<?php elseif ($s['status'] === 'cancellation_pending'): ?>
  <div class="alert info">This service ends on <?= date_fmt($s['next_due_date']) ?>.</div>
<?php endif; ?>

<div class="stats three">
  <div class="stat"><span>Status</span><strong style="font-size:18px"><?= badge($s['status']) ?></strong><small><?= $s['activation_date'] ? 'Since ' . date_fmt($s['activation_date']) : 'Not activated' ?></small></div>
  <div class="stat"><span>Billing</span><strong><?= money($s['price']) ?></strong><small><?= e(cycle_label($s['billing_cycle'])) ?> · next due <?= date_fmt($s['next_due_date']) ?></small></div>
  <div class="stat"><span>Disk usage</span><strong><?= $s['disk_used_mb'] !== null ? e(number_format((float) $s['disk_used_mb'], 0)) . ' MB' : '—' ?></strong><small><?= $s['disk_limit_mb'] !== null ? 'of ' . e(number_format((float) $s['disk_limit_mb'], 0)) . ' MB' : '' ?></small></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Actions</h3></div>
    <div class="actions">
      <?php if ($active || $s['status'] === 'suspended'): ?>
        <form method="post" action="<?= url('/client/services/' . $s['id'] . '/renew') ?>" class="inline" data-confirm="Generate a renewal invoice for the next <?= e(strtolower(cycle_label($s['billing_cycle']))) ?> period?" data-confirm-style="primary"><?= csrf_field() ?><button class="btn primary">Renew now</button></form>
      <?php endif; ?>
      <?php if ($active): ?>
        <form method="post" action="<?= url('/client/services/' . $s['id'] . '/login') ?>" class="inline" target="_blank"><?= csrf_field() ?><button class="btn ghost"><?= $s['type'] === 'email' ? 'Webmail' : 'Control panel' ?></button></form>
      <?php endif; ?>
    </div>

    <?php if ($active && $altPlans): ?>
    <form method="post" action="<?= url('/client/services/' . $s['id'] . '/change-plan') ?>" style="margin-top:14px" data-confirm="Change plan? Upgrades are charged pro-rata and take effect after payment." data-confirm-style="primary">
      <?= csrf_field() ?>
      <label class="field"><span>Upgrade / downgrade plan</span>
        <div style="display:flex;gap:8px">
          <select name="plan_id" required style="flex:1">
            <?php foreach ($altPlans as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?><?= $p['price_monthly'] !== null ? ' — ' . money($p['price_monthly']) . '/mo' : '' ?></option><?php endforeach; ?>
          </select>
          <button class="btn ghost" type="submit">Change</button>
        </div>
      </label>
    </form>
    <?php endif; ?>

    <h4>Need help?</h4>
    <p class="small">Questions about this service? <a href="<?= url('/client/support/create') ?>">Open a support ticket</a> and we'll help.</p>
  </div>

  <div class="card">
    <div class="card-head"><h3>Related invoices</h3></div>
    <?php if (!$invoices): ?><div class="empty">No invoices for this service.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Invoice</th><th>Due</th><th class="text-right">Total</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($invoices as $i): ?>
        <tr><td><a href="<?= url('/client/invoices/' . $i['id']) ?>"><?= e($i['number']) ?></a></td><td><?= date_fmt($i['due_date']) ?></td><td class="text-right"><?= money($i['total'], $i['currency']) ?></td><td><?= badge($i['status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>
