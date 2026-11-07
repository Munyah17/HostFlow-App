<?php
$open = in_array($invoice['status'], ['draft', 'pending', 'overdue'], true);
?>
<?= view('components.heading', [
    'title' => 'Invoice ' . $invoice['number'],
    'subtitle' => ($invoice['business_name'] ?: $invoice['full_name']) . ' · issued ' . date_fmt($invoice['issue_date']) . ' · due ' . date_fmt($invoice['due_date']),
    'actions' => '<a class="btn ghost" href="' . url('/billing/invoices/' . $invoice['id'] . '/pdf') . '" target="_blank">PDF</a>'
        . '<a class="btn ghost" href="' . url('/customers/view/' . $invoice['customer_id']) . '">Customer</a>'
        . '<a class="btn ghost" href="' . url('/billing/invoices') . '">All invoices</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><span>Status</span><strong class="stat-badge"><?= badge($invoice['status']) ?></strong><small><?= $invoice['paid_at'] ? 'Paid ' . datetime_fmt($invoice['paid_at']) : 'Due ' . date_fmt($invoice['due_date']) ?></small></div>
  <div class="stat"><span>Total</span><strong><?= money($invoice['total'], $invoice['currency']) ?></strong><small><?= money($invoice['amount_paid'], $invoice['currency']) ?> received</small></div>
  <div class="stat"><span>Balance due</span><strong><?= money($balance, $invoice['currency']) ?></strong><small><?= $payable ? 'Awaiting payment' : 'Nothing owing' ?></small></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Items</h3></div>
    <div class="table-scroll"><table>
      <thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit</th><th class="text-right">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td class="wrap"><?= e($it['description']) ?><br><small class="muted"><?= e(status_label($it['item_type'])) ?><?= $it['domain'] ? ' · ' . e($it['domain']) : '' ?><?= $it['period_start'] ? ' · ' . date_fmt($it['period_start']) . ' → ' . date_fmt($it['period_end']) : '' ?></small></td>
          <td class="text-right"><?= e(rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.')) ?></td>
          <td class="text-right"><?= money($it['unit_price'], $invoice['currency']) ?></td>
          <td class="text-right"><?= money($it['amount'], $invoice['currency']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="totals">
      <div><span>Subtotal</span><span><?= money($invoice['subtotal'], $invoice['currency']) ?></span></div>
      <?php if ((float) $invoice['discount'] > 0): ?><div><span>Discount</span><span>−<?= money($invoice['discount'], $invoice['currency']) ?></span></div><?php endif; ?>
      <div class="grand"><span>Total</span><span><?= money($invoice['total'], $invoice['currency']) ?></span></div>
      <div><span>Paid</span><span><?= money($invoice['amount_paid'], $invoice['currency']) ?></span></div>
      <div class="grand"><span>Balance</span><span><?= money($balance, $invoice['currency']) ?></span></div>
    </div>
    <?php if ($invoice['notes']): ?><h4>Notes</h4><p class="small prewrap"><?= e($invoice['notes']) ?></p><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Actions</h3></div>
    <div class="events">
      <div><b>Bill to</b><small><?= e($invoice['business_name'] ?: $invoice['full_name']) ?><br><?= e($invoice['email']) ?><?= $invoice['phone'] ? '<br>' . e($invoice['phone']) : '' ?></small></div>
      <?php if ($invoice['payment_reference']): ?><div><b>Payment reference</b><small class="mono"><?= e($invoice['payment_reference']) ?></small></div><?php endif; ?>
    </div>
    <div class="form-actions">
      <?php if ($invoice['status'] === 'draft'): ?>
        <form method="post" action="<?= url('/billing/invoices/' . $invoice['id'] . '/publish') ?>" class="inline" data-confirm="Publish and email this invoice to the customer?" data-confirm-style="primary"><?= csrf_field() ?><button class="btn primary">Publish &amp; send</button></form>
      <?php endif; ?>
      <?php if ($payable): ?>
        <form method="post" action="<?= url('/billing/invoices/' . $invoice['id'] . '/send') ?>" class="inline" data-confirm="Email this invoice to <?= e($invoice['email']) ?> now?" data-confirm-style="primary"><?= csrf_field() ?><button class="btn ghost">Email invoice</button></form>
      <?php endif; ?>
      <?php if ($open && !$payments): ?>
        <form method="post" action="<?= url('/billing/invoices/' . $invoice['id'] . '/cancel') ?>" class="inline" data-confirm="Cancel this invoice?" data-confirm-button="Cancel invoice"><?= csrf_field() ?><button class="btn danger">Cancel invoice</button></form>
      <?php endif; ?>
    </div>

    <?php if (in_array($invoice['status'], ['pending', 'overdue'], true)): ?>
    <form method="post" action="<?= url('/billing/invoices/' . $invoice['id'] . '/due-date') ?>" class="mt-14">
      <?= csrf_field() ?>
      <label class="field"><span>Change due date</span>
        <span class="inline-form"><input type="date" name="due_date" value="<?= e($invoice['due_date']) ?>" required><button class="btn ghost" type="submit">Save</button></span>
        <?= field_error('due_date') ?>
      </label>
    </form>
    <?php endif; ?>

    <?php if ($payable): ?>
    <div class="card-section">
    <h4>Record a manual payment</h4>
    <form method="post" action="<?= url('/payments/manual') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
      <div class="form-grid">
        <label class="field"><span>Amount *</span><input type="number" name="amount" value="<?= e(number_format($balance, 2, '.', '')) ?>" min="0.01" max="<?= e(number_format($balance, 2, '.', '')) ?>" step="0.01" required></label>
        <label class="field"><span>Method *</span>
          <select name="method"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="mobile_money">Mobile money (manual)</option><option value="other">Other</option></select>
        </label>
        <label class="field"><span>Reference</span><input type="text" name="reference" maxlength="120" placeholder="Receipt / transfer ref"></label>
        <label class="field"><span>Payment date *</span><input type="date" name="paid_at" value="<?= e(today()) ?>" required></label>
      </div>
      <label class="field"><span>Note</span><input type="text" name="note" maxlength="500"></label>
      <div class="form-actions"><button type="submit" class="btn primary">Record payment</button><a class="btn ghost" href="<?= url('/payments') ?>">All payments</a></div>
    </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($payments): ?>
<div class="card">
  <div class="card-head"><h3>Payments</h3></div>
  <?= view('admin.payments._table', ['payments' => $payments], null) ?>
</div>
<?php endif; ?>
