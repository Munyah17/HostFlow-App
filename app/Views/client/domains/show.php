<?php $live = in_array($d['status'], ['active', 'expired'], true); ?>
<?= view('components.heading', [
    'title' => $d['domain'],
    'subtitle' => 'Domain registration',
    'actions' => '<a class="btn ghost" href="' . url('/client/domains') . '">All domains</a>',
], null) ?>

<?php if ($d['status'] === 'pending_payment'): ?>
  <div class="alert info"><b>Awaiting payment.</b> Pay the order invoice to register this domain.</div>
<?php elseif ($d['status'] === 'pending_registration'): ?>
  <div class="alert info"><b>Being registered.</b> Payment received — we're registering your domain now.</div>
<?php elseif ($d['status'] === 'expired'): ?>
  <div class="alert error"><b>This domain expired</b> on <?= date_fmt($d['expiry_date']) ?>. Renew now to restore it — after the registrar's grace period it may be released.</div>
<?php elseif ($d['status'] === 'transferred_away'): ?>
  <div class="alert info">This domain was transferred to another registrar.</div>
<?php endif; ?>

<div class="stats three">
  <div class="stat"><span>Status</span><strong style="font-size:18px"><?= badge($d['status']) ?></strong><small><?= $d['registrar'] ? 'At ' . e($d['registrar']) : '' ?></small></div>
  <div class="stat"><span>Registered</span><strong><?= date_fmt($d['registered_at']) ?></strong><small></small></div>
  <div class="stat"><span>Expiry date</span><strong><?= date_fmt($d['expiry_date']) ?></strong><small><?= $d['auto_renew'] ? 'Renewal invoiced automatically' : 'Manual renewal' ?></small></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Actions</h3></div>
    <div class="actions">
      <?php if ($live): ?>
        <form method="post" action="<?= url('/client/domains/' . $d['id'] . '/renew') ?>" class="inline" data-confirm="Generate a renewal invoice for <?= e($d['domain']) ?>?" data-confirm-style="primary"><?= csrf_field() ?><button class="btn primary">Renew now</button></form>
      <?php endif; ?>
    </div>
    <dl class="details" style="margin-top:14px">
      <div><dt>Nameservers</dt><dd><?= $d['nameservers'] ? e($d['nameservers']) : '—' ?></dd></div>
      <div><dt>Renewal price</dt><dd><?= $d['renewal_price'] > 0 ? money($d['renewal_price']) . ' / year' : '—' ?></dd></div>
    </dl>
    <h4>Need help?</h4>
    <p class="small">Questions about this domain? <a href="<?= url('/client/support/create') ?>">Open a support ticket</a> and we'll help.</p>
  </div>

  <div class="card">
    <div class="card-head"><h3>Related invoices</h3></div>
    <?php if (!$invoices): ?><div class="empty">No invoices for this domain.</div><?php else: ?>
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
