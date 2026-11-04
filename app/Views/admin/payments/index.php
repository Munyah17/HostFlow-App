<?= view('components.heading', [
    'title' => 'Payments',
    'subtitle' => money($stats['month']) . ' collected this month',
], null) ?>

<div class="stats three">
  <div class="stat"><span>Collected this month</span><strong><?= money($stats['month']) ?></strong><small><?= money($stats['today']) ?> today</small></div>
  <div class="stat"><a href="<?= url('/payments', ['status' => 'pending']) ?>"><span>Pending gateway payments</span><strong><?= (int) $stats['pending'] ?></strong><small>Awaiting confirmation</small></a></div>
  <div class="stat"><a href="<?= url('/payments', ['status' => 'failed']) ?>"><span>Failed (7 days)</span><strong><?= (int) $stats['failed7'] ?></strong><small>Gateway attempts</small></a></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Payment history</h3></div>
    <form class="filters" method="get" action="<?= url('/payments') ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search reference, invoice or customer…">
      <select name="status" data-autosubmit>
        <option value="">All statuses</option>
        <?php foreach (['pending', 'completed', 'failed', 'cancelled', 'refunded'] as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option><?php endforeach; ?>
      </select>
      <select name="gateway" data-autosubmit>
        <option value="">All methods</option>
        <?php foreach ($gateways as $g): ?><option value="<?= e($g['gateway']) ?>" <?= $gateway === $g['gateway'] ? 'selected' : '' ?>><?= e(ucfirst($g['gateway'])) ?></option><?php endforeach; ?>
      </select>
      <button class="btn ghost" type="submit">Search</button>
    </form>
    <?= view('admin.payments._table', ['payments' => $payments], null) ?>
    <?= view('components.pagination', ['p' => $p], null) ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Record a payment</h3><p>Cash, bank transfer or manual mobile money against an open invoice.</p></div>
    <form method="post" action="<?= url('/payments/manual') ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Invoice *</span>
        <select name="invoice_id" required>
          <option value="">Select an open invoice…</option>
          <?php foreach ($payableInvoices as $i): ?>
            <option value="<?= (int) $i['id'] ?>" <?= (int) old('invoice_id') === (int) $i['id'] ? 'selected' : '' ?>><?= e($i['number'] . ' — ' . ($i['business_name'] ?: $i['full_name']) . ' (' . money($i['balance'], $i['currency']) . ')') ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error('invoice_id') ?>
      </label>
      <div class="form-grid">
        <label class="field"><span>Amount *</span><input type="number" name="amount" value="<?= e(old('amount')) ?>" min="0.01" step="0.01" required><?= field_error('amount') ?></label>
        <label class="field"><span>Method *</span>
          <select name="method">
            <?php foreach (\App\Services\PaymentService::MANUAL_METHODS as $key => $label): ?><option value="<?= $key ?>" <?= old('method') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="field"><span>Reference *</span><input type="text" name="reference" value="<?= e(old('reference')) ?>" maxlength="120" required placeholder="Receipt / transfer ref"><?= field_error('reference') ?></label>
        <label class="field"><span>Payment date *</span><input type="date" name="paid_at" value="<?= e(old('paid_at', today())) ?>" required><?= field_error('paid_at') ?></label>
      </div>
      <label class="field"><span>Note</span><input type="text" name="note" value="<?= e(old('note')) ?>" maxlength="500"><?= field_error('note') ?></label>
      <div class="form-actions"><button type="submit" class="btn primary">Record payment</button></div>
    </form>
  </div>
</div>

<?php
$pending = array_filter($payments, fn ($pm) => $pm['status'] === 'pending' && $pm['gateway'] !== 'manual');
if ($pending): ?>
<div class="card">
  <div class="card-head"><h3>Pending gateway payments</h3><p>Re-check with the payment gateway to confirm status.</p></div>
  <div class="table-scroll"><table>
    <thead><tr><th>Reference</th><th>Invoice</th><th class="text-right">Amount</th><th>Gateway</th><th>Initiated</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pending as $pm): ?>
      <tr>
        <td><span class="mono"><?= e($pm['reference']) ?></span></td>
        <td><a href="<?= url('/billing/invoices/view/' . $pm['invoice_id']) ?>"><?= e($pm['number']) ?></a></td>
        <td class="text-right"><?= money($pm['amount'], $pm['currency']) ?></td>
        <td><?= e(ucfirst($pm['gateway'])) ?><?= $pm['method'] ? ' (' . e($pm['method']) . ')' : '' ?></td>
        <td><small><?= datetime_fmt($pm['created_at']) ?></small></td>
        <td class="text-right"><form method="post" action="<?= url('/payments/' . $pm['id'] . '/verify') ?>" class="inline"><?= csrf_field() ?><button class="btn small ghost" type="submit">Check status</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
