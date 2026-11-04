<?php
use App\Core\Auth;

$active = $s['status'] === 'active';
$suspended = $s['status'] === 'suspended';
$awaiting = in_array($s['status'], ['provisioning', 'provisioning_failed'], true);
$open = !in_array($s['status'], ['terminated', 'cancelled'], true);
?>
<?= view('components.heading', [
    'title' => $s['domain'],
    'subtitle' => ($s['type'] === 'email' ? 'Email hosting' : 'Web hosting') . ' · ' . $s['plan_name'] . ' · ' . ($s['business_name'] ?: $s['full_name']),
    'actions' => '<a class="btn ghost" href="' . url('/customers/view/' . $s['customer_id']) . '">Customer</a>'
        . '<a class="btn ghost" href="' . url($s['type'] === 'email' ? '/email-hosting' : '/hosting') . '">All services</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><span>Status</span><strong style="font-size:18px"><?= badge($s['status']) ?></strong><small><?= $s['provider_status'] ? 'Server: ' . e($s['provider_status']) : 'Not provisioned' ?></small></div>
  <div class="stat"><span>Billing</span><strong><?= money($s['price']) ?></strong><small><?= e(cycle_label($s['billing_cycle'])) ?> · next due <?= date_fmt($s['next_due_date']) ?></small></div>
  <div class="stat"><span>Server sync</span><strong style="font-size:18px"><?= badge($s['sync_status']) ?></strong><small><?= $s['last_synced_at'] ? 'Checked ' . datetime_fmt($s['last_synced_at']) : 'Never checked' ?></small></div>
</div>

<?php if ($s['last_error']): ?><div class="alert error"><b>Last error:</b> <?= e($s['last_error']) ?></div><?php endif; ?>
<?php if ($s['sync_message'] && $s['sync_status'] !== 'in_sync'): ?><div class="alert warning"><b>Sync:</b> <?= e($s['sync_message']) ?></div><?php endif; ?>
<?php if ($suspended): ?><div class="alert warning"><b>Suspended:</b> <?= e($s['suspension_reason']) ?> · <?= $s['suspension_type'] === 'billing' ? 'Overdue invoice' : 'Manual' ?> · <?= datetime_fmt($s['suspended_at']) ?></div><?php endif; ?>
<?php if ($s['status'] === 'cancellation_pending'): ?><div class="alert info">This service is set to terminate at the end of the paid period<?= $s['termination_flagged_at'] ? ' (' . date_fmt($s['termination_flagged_at']) . ')' : '' ?>.</div><?php endif; ?>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Service details</h3></div>
    <dl class="details">
      <div><dt>Domain</dt><dd><?= e($s['domain']) ?></dd></div>
      <div><dt>Plan</dt><dd><?= e($s['plan_name']) ?><?= $s['provider_package'] ? ' <small class="muted">(' . e($s['provider_package']) . ')</small>' : '' ?></dd></div>
      <div><dt>Customer</dt><dd><a href="<?= url('/customers/view/' . $s['customer_id']) ?>"><?= e($s['business_name'] ?: $s['full_name']) ?></a></dd></div>
      <div><dt>Provider</dt><dd><?= e($s['provider_name'] ?: 'None assigned') ?><?= $s['provider_username'] ? ' · <span class="mono">' . e($s['provider_username']) . '</span>' : '' ?></dd></div>
      <div><dt>Activated</dt><dd><?= date_fmt($s['activation_date']) ?></dd></div>
      <div><dt>Next due</dt><dd><?= date_fmt($s['next_due_date']) ?></dd></div>
      <div><dt>Disk usage</dt><dd><?= $s['disk_used_mb'] !== null ? e(number_format((float) $s['disk_used_mb'], 0)) . ' / ' . ($s['disk_limit_mb'] !== null ? e(number_format((float) $s['disk_limit_mb'], 0)) : '∞') . ' MB' : '—' ?></dd></div>
      <div><dt>Domain expiry</dt><dd><?= date_fmt($s['domain_expiry_date']) ?><?= $s['domain_registrar'] ? ' <small class="muted">(' . e($s['domain_registrar']) . ')</small>' : '' ?></dd></div>
    </dl>
    <?php if ($s['notes']): ?><h4>Staff notes</h4><p class="small" style="white-space:pre-wrap"><?= e($s['notes']) ?></p><?php endif; ?>

    <h4>Actions</h4>
    <div class="actions">
      <?php if ($awaiting): ?>
        <form method="post" action="<?= url('/hosting/' . $s['id'] . '/provision') ?>" class="inline" data-confirm="Create the hosting account on the server now?" data-confirm-style="primary"><?= csrf_field() ?><button class="btn primary">Provision now</button></form>
      <?php endif; ?>
      <?php if ($active && $s['provider_username']): ?>
        <form method="post" action="<?= url('/hosting/' . $s['id'] . '/login') ?>" class="inline" target="_blank"><?= csrf_field() ?><button class="btn ghost">Log in to panel</button></form>
      <?php endif; ?>
      <?php if ($suspended): ?>
        <form method="post" action="<?= url('/hosting/' . $s['id'] . '/unsuspend') ?>" class="inline" data-confirm="Reactivate this service on the server?" data-confirm-style="primary"><?= csrf_field() ?><button class="btn primary">Unsuspend</button></form>
      <?php endif; ?>
      <?php if ($active): ?>
        <form method="post" action="<?= url('/hosting/' . $s['id'] . '/suspend') ?>" class="inline" data-confirm="Suspend this account? Enter the reason below first." data-confirm-type="suspend" data-confirm-button="Suspend"><?= csrf_field() ?><input type="hidden" name="reason" value="Suspended by staff"><button class="btn danger">Suspend</button></form>
      <?php endif; ?>
      <?php if ($s['provider_username']): ?>
        <form method="post" action="<?= url('/hosting/' . $s['id'] . '/sync') ?>" class="inline"><?= csrf_field() ?><button class="btn ghost">Sync with server</button></form>
      <?php endif; ?>
      <?php if (in_array($s['status'], ['active', 'suspended'], true)): ?>
        <form method="post" action="<?= url('/hosting/' . $s['id'] . '/renew') ?>" class="inline" data-confirm="Generate the renewal invoice now?" data-confirm-style="primary"><?= csrf_field() ?><button class="btn ghost">Invoice renewal</button></form>
      <?php endif; ?>
    </div>

    <?php if ($active && $altPlans): ?>
    <form method="post" action="<?= url('/hosting/' . $s['id'] . '/change-plan') ?>" style="margin-top:14px" data-confirm="Change the plan? Upgrades create a pro-rata invoice; downgrades apply at the next renewal." data-confirm-style="primary">
      <?= csrf_field() ?>
      <label class="field"><span>Change plan</span>
        <div style="display:flex;gap:8px">
          <select name="plan_id" required style="flex:1">
            <?php foreach ($altPlans as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
          <button class="btn ghost" type="submit">Change</button>
        </div>
      </label>
    </form>
    <?php endif; ?>

    <?php if (!$s['provider_username'] && $providers): ?>
    <form method="post" action="<?= url('/hosting/' . $s['id'] . '/link') ?>" style="margin-top:14px">
      <?= csrf_field() ?>
      <label class="field"><span>Link an existing server account</span>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <select name="provider_id" required style="flex:1;min-width:150px"><option value="">Provider…</option><?php foreach ($providers as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
          <input type="text" name="username" placeholder="cPanel username" required style="flex:1;min-width:130px;padding:11px;border:1px solid var(--line);border-radius:8px">
          <button class="btn ghost" type="submit">Link</button>
        </div>
        <?= field_error('username') ?>
      </label>
    </form>
    <?php endif; ?>

    <?php if ($open): ?>
    <div class="actions" style="margin-top:14px;border-top:1px solid var(--line);padding-top:14px">
      <?php if ($s['status'] !== 'cancellation_pending'): ?>
        <form method="post" action="<?= url('/hosting/' . $s['id'] . '/cancel') ?>" class="inline" data-confirm="<?= in_array($s['status'], ['active', 'suspended'], true) ? 'Cancel at the end of the paid period? The account stays up until then.' : 'Cancel this service? It was never provisioned.' ?>"><button class="btn ghost"><?= in_array($s['status'], ['active', 'suspended'], true) ? 'Cancel at period end' : 'Cancel service' ?></button><?= csrf_field() ?></form>
      <?php endif; ?>
      <?php if (Auth::isSuper()): ?>
        <form method="post" action="<?= url('/hosting/' . $s['id'] . '/terminate') ?>" class="inline" data-confirm="Permanently delete this hosting account and its data on the server? This cannot be undone." data-confirm-type="<?= e($s['domain']) ?>" data-confirm-button="Terminate"><?= csrf_field() ?><button class="btn danger">Terminate</button></form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Edit details</h3></div>
    <form method="post" action="<?= url('/hosting/update/' . $s['id']) ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Domain</span><input type="text" name="domain" value="<?= e($s['domain']) ?>" required maxlength="253"><?= field_error('domain') ?></label>
      <div class="form-grid">
        <label class="field"><span>Price</span><input type="number" name="price" value="<?= e($s['price']) ?>" min="0" step="0.01" required><?= field_error('price') ?></label>
        <label class="field"><span>Billing cycle</span>
          <select name="billing_cycle">
            <?php foreach (\App\Services\BillingCycle::LABELS as $key => $label): ?><option value="<?= $key ?>" <?= $s['billing_cycle'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <?= field_error('billing_cycle') ?>
        </label>
        <label class="field"><span>Next due date</span><input type="date" name="next_due_date" value="<?= e($s['next_due_date']) ?>"><?= field_error('next_due_date') ?></label>
        <label class="field"><span>Domain expiry</span><input type="date" name="domain_expiry_date" value="<?= e($s['domain_expiry_date']) ?>"><?= field_error('domain_expiry_date') ?></label>
      </div>
      <label class="field"><span>Domain registrar</span><input type="text" name="domain_registrar" value="<?= e($s['domain_registrar']) ?>" maxlength="120"><?= field_error('domain_registrar') ?></label>
      <label class="field"><span>Notes (staff only)</span><textarea name="notes" maxlength="5000"><?= e($s['notes']) ?></textarea><?= field_error('notes') ?></label>
      <div class="form-actions"><button type="submit" class="btn primary">Save</button></div>
    </form>
  </div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Billing history</h3></div>
    <?php if (!$items): ?><div class="empty">No invoice items for this service.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Invoice</th><th>Item</th><th class="text-right">Amount</th><th>Period</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><a href="<?= url('/billing/invoices/view/' . $it['invoice_id']) ?>"><?= e($it['number']) ?></a></td>
          <td class="wrap"><?= e(str_limit($it['description'], 60)) ?><br><small class="muted"><?= e(status_label($it['item_type'])) ?></small></td>
          <td class="text-right"><?= money($it['amount']) ?></td>
          <td><small><?= $it['period_start'] ? date_fmt($it['period_start']) . ' → ' . date_fmt($it['period_end']) : '—' ?></small></td>
          <td><?= badge($it['invoice_status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3>Activity</h3></div>
    <?php if (!$logs): ?><div class="empty">No activity recorded.</div><?php else: ?>
    <div class="events">
      <?php foreach ($logs as $l): ?><div><b><?= e($l['action']) ?> <?= badge($l['status']) ?></b><small><?= datetime_fmt($l['created_at']) ?> · <?= e(str_limit($l['message'], 120)) ?></small></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
