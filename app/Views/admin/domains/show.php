<?php
$pending = in_array($d['status'], ['pending_payment', 'pending_registration'], true);
$live = in_array($d['status'], ['active', 'expired'], true);
$closed = in_array($d['status'], ['transferred_away', 'cancelled'], true);
?>
<?= view('components.heading', [
    'title' => $d['domain'],
    'subtitle' => ($d['business_name'] ?: $d['full_name']) . ' · ' . status_label($d['status']),
    'actions' => '<a class="btn ghost" href="' . url('/customers/view/' . $d['customer_id']) . '">Customer</a><a class="btn ghost" href="' . url('/domains') . '">All domains</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><span>Status</span><strong class="stat-badge"><?= badge($d['status']) ?></strong><small><?= $d['registrar'] ?: 'Registrar not recorded' ?></small></div>
  <div class="stat"><span>Registered</span><strong><?= date_fmt($d['registered_at']) ?></strong><small><?= $d['registrar_reference'] ? 'Ref ' . e($d['registrar_reference']) : '' ?></small></div>
  <div class="stat"><span>Expiry</span><strong><?= date_fmt($d['expiry_date']) ?></strong><small><?= $d['auto_renew'] ? 'Auto-renew invoicing on' : 'Auto-renew off' ?></small></div>
</div>

<?php if ($d['status'] === 'pending_registration'): ?><div class="alert warning"><b>Awaiting registration:</b> payment received — register this domain at the registrar, then mark it registered below.</div><?php endif; ?>
<?php if ($d['status'] === 'pending_payment'): ?><div class="alert info">Awaiting payment of the registration invoice.</div><?php endif; ?>
<?php if ($d['status'] === 'expired'): ?><div class="alert error"><b>Expired</b> <?= date_fmt($d['expiry_date']) ?> — the client can still renew; after the registrar grace period it may be released.</div><?php endif; ?>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Domain details</h3></div>
    <dl class="details">
      <div><dt>Domain</dt><dd><?= e($d['domain']) ?></dd></div>
      <div><dt>Customer</dt><dd><a href="<?= url('/customers/view/' . $d['customer_id']) ?>"><?= e($d['business_name'] ?: $d['full_name']) ?></a></dd></div>
      <div><dt>Linked service</dt><dd><?= $d['service_domain'] ? '<a href="' . url('/hosting/view/' . $d['service_id']) . '">' . e($d['service_domain']) . '</a>' : '—' ?></dd></div>
      <div><dt>Registrar</dt><dd><?= e($d['registrar'] ?: '—') ?><?= $d['registrar_reference'] ? ' · <span class="mono">' . e($d['registrar_reference']) . '</span>' : '' ?></dd></div>
      <div><dt>Nameservers</dt><dd><?= $d['nameservers'] ? e($d['nameservers']) : '—' ?></dd></div>
      <div><dt>Renewal price</dt><dd><?= $d['renewal_price'] > 0 ? money($d['renewal_price']) . ' <small class="muted">/ year</small>' : '—' ?></dd></div>
    </dl>
    <?php if ($d['notes']): ?><h4>Staff notes</h4><p class="small prewrap"><?= e($d['notes']) ?></p><?php endif; ?>

    <?php if (!$closed): ?>
    <h4>Actions</h4>
    <div class="actions">
      <?php if ($live): ?>
        <form method="post" action="<?= url('/domains/' . $d['id'] . '/renew') ?>" class="inline" data-confirm="Generate the renewal invoice now?" data-confirm-style="primary"><?= csrf_field() ?><button class="btn primary">Invoice renewal</button></form>
        <form method="post" action="<?= url('/domains/' . $d['id'] . '/status') ?>" class="inline" data-confirm="Mark this domain as transferred away to another registrar?"><?= csrf_field() ?><input type="hidden" name="status" value="transferred_away"><button class="btn ghost">Transferred away</button></form>
      <?php endif; ?>
      <form method="post" action="<?= url('/domains/' . $d['id'] . '/status') ?>" class="inline" data-confirm="Cancel this domain record?"><?= csrf_field() ?><input type="hidden" name="status" value="cancelled"><button class="btn danger">Cancel domain</button></form>
    </div>

    <?php if ($pending): ?>
    <form method="post" action="<?= url('/domains/' . $d['id'] . '/register') ?>" class="action-section" data-confirm="Mark this domain as registered at the registrar?" data-confirm-style="primary">
      <?= csrf_field() ?>
      <h4>Mark as registered</h4>
      <div class="form-grid">
        <label class="field"><span>Registration date</span><input type="date" name="registered_at" value="<?= e(today()) ?>"></label>
        <label class="field"><span>Expiry date</span><input type="date" name="expiry_date" value="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>"></label>
        <label class="field"><span>Registrar</span><input type="text" name="registrar" value="<?= e($d['registrar']) ?>" maxlength="120" placeholder="e.g. ZISP, Namecheap"></label>
        <label class="field"><span>Registrar reference</span><input type="text" name="registrar_reference" value="<?= e($d['registrar_reference']) ?>" maxlength="190"></label>
      </div>
      <label class="field"><span>Nameservers (comma-separated)</span><input type="text" name="nameservers" value="<?= e($d['nameservers']) ?>" maxlength="500" placeholder="ns1.example.com, ns2.example.com"></label>
      <div class="form-actions"><button type="submit" class="btn primary">Confirm registration</button></div>
    </form>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Edit details</h3></div>
    <form method="post" action="<?= url('/domains/update/' . $d['id']) ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Linked hosting service</span>
        <select name="service_id">
          <option value="">None</option>
          <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $d['service_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['domain']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <div class="form-grid">
        <label class="field"><span>Registrar</span><input type="text" name="registrar" value="<?= e($d['registrar']) ?>" maxlength="120"></label>
        <label class="field"><span>Registrar reference</span><input type="text" name="registrar_reference" value="<?= e($d['registrar_reference']) ?>" maxlength="190"></label>
        <label class="field"><span>Registered</span><input type="date" name="registered_at" value="<?= e($d['registered_at']) ?>"></label>
        <label class="field"><span>Expiry date</span><input type="date" name="expiry_date" value="<?= e($d['expiry_date']) ?>"></label>
        <label class="field"><span>Renewal price / year</span><input type="number" name="renewal_price" value="<?= e($d['renewal_price']) ?>" min="0" step="0.01"></label>
      </div>
      <label class="field"><span>Nameservers</span><input type="text" name="nameservers" value="<?= e($d['nameservers']) ?>" maxlength="500"></label>
      <label class="check"><input type="checkbox" name="auto_renew" value="1" <?= $d['auto_renew'] ? 'checked' : '' ?>> Invoice renewals automatically before expiry</label>
      <label class="field"><span>Notes (staff only)</span><textarea name="notes" maxlength="5000"><?= e($d['notes']) ?></textarea></label>
      <div class="form-actions"><button type="submit" class="btn primary">Save</button></div>
    </form>
  </div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Billing history</h3></div>
    <?php if (!$items): ?><div class="empty">No invoice items for this domain.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Invoice</th><th>Item</th><th class="text-right">Amount</th><th>Period</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><a href="<?= url('/billing/invoices/view/' . $it['invoice_id']) ?>"><?= e($it['number']) ?></a></td>
          <td class="wrap"><?= e(str_limit($it['description'], 60)) ?></td>
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
