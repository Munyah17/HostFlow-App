<?= view('components.heading', [
    'title' => 'Invoice ' . $invoice['number'],
    'subtitle' => 'Issued ' . date_fmt($invoice['issue_date']) . ' · due ' . date_fmt($invoice['due_date']),
    'actions' => '<a class="btn ghost" href="' . url('/client/invoices/' . $invoice['id'] . '/pdf') . '" target="_blank">PDF</a><a class="btn ghost" href="' . url('/client/invoices') . '">All invoices</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><span>Status</span><strong class="stat-badge"><?= badge($invoice['status']) ?></strong><small><?= $invoice['paid_at'] ? 'Paid ' . datetime_fmt($invoice['paid_at']) : 'Due ' . date_fmt($invoice['due_date']) ?></small></div>
  <div class="stat"><span>Total</span><strong><?= money($invoice['total'], $invoice['currency']) ?></strong><small><?= money($invoice['amount_paid'], $invoice['currency']) ?> paid</small></div>
  <div class="stat"><span>Balance due</span><strong><?= money($balance, $invoice['currency']) ?></strong><small><?= $payable ? 'Awaiting payment' : 'Settled' ?></small></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Items</h3></div>
    <div class="table-scroll"><table>
      <thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit</th><th class="text-right">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td class="wrap"><?= e($it['description']) ?><br><small class="muted"><?= e(status_label($it['item_type'])) ?><?= $it['period_start'] ? ' · ' . date_fmt($it['period_start']) . ' → ' . date_fmt($it['period_end']) : '' ?></small></td>
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
      <div class="grand"><span>Balance due</span><span><?= money($balance, $invoice['currency']) ?></span></div>
    </div>
  </div>

  <div class="card">
    <?php if ($payable): ?>
      <div class="card-head"><h3>Pay <?= money($balance, $invoice['currency']) ?></h3></div>
      <?php if ($paynow || $stripe): ?>
        <div class="stack">
        <?php if ($paynow): ?>
        <form method="post" action="<?= url('/client/invoices/' . $invoice['id'] . '/pay') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="gateway" value="paynow">
          <input type="hidden" name="method" value="web">
          <button class="btn primary btn-block" type="submit">Pay with Paynow</button>
        </form>
        <?php endif; ?>
        <?php if ($stripe): ?>
        <form method="post" action="<?= url('/client/invoices/' . $invoice['id'] . '/pay') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="gateway" value="stripe">
          <button class="btn<?= $paynow ? ' ghost' : ' primary' ?> btn-block" type="submit">Pay by card (Stripe)</button>
        </form>
        <?php endif; ?>
        </div>
        <?php if ($paynow && $mobile): ?>
        <form method="post" action="<?= url('/client/invoices/' . $invoice['id'] . '/pay') ?>" class="mt-12">
          <?= csrf_field() ?>
          <input type="hidden" name="gateway" value="paynow">
          <input type="hidden" name="method" value="mobile">
          <div class="form-grid">
            <label class="field"><span>Mobile money</span>
              <select name="mobile_method"><option value="ecocash">EcoCash</option><option value="onemoney">OneMoney</option></select>
            </label>
            <label class="field"><span>Phone number</span><input type="tel" name="phone" placeholder="0772 123 456" required></label>
          </div>
          <button class="btn ghost btn-block" type="submit">Send payment prompt to phone</button>
        </form>
        <?php endif; ?>
      <?php else: ?>
        <p class="small muted">Online payments are not configured yet — please use the manual option below.</p>
      <?php endif; ?>
      <?php if ($bankInstructions !== ''): ?>
        <h4 class="mt-18">Manual payment</h4>
        <p class="small prewrap"><?= e($bankInstructions) ?></p>
        <p class="help">Use reference <span class="mono"><?= e($invoice['payment_reference'] ?: $invoice['number']) ?></span> so we can match your payment.</p>
      <?php endif; ?>
    <?php else: ?>
      <div class="card-head"><h3>Payments</h3></div>
      <?php if (!$payments): ?><div class="empty">This invoice is settled.</div><?php else: ?>
      <div class="table-scroll"><table>
        <thead><tr><th>Reference</th><th class="text-right">Amount</th><th>Method</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $pm): ?>
          <tr><td><span class="mono"><?= e($pm['reference']) ?></span></td><td class="text-right"><?= money($pm['amount'], $pm['currency']) ?></td><td><?= e(\App\Services\PaymentService::methodLabel($pm['gateway'], $pm['method'])) ?></td><td><small><?= datetime_fmt($pm['paid_at'] ?: $pm['created_at']) ?></small></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
