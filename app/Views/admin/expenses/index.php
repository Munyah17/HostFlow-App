<?= view('components.heading', [
    'title' => 'Expenses',
    'subtitle' => 'Operating cost ledger · ' . setting('business_name'),
], null) ?>

<div class="stats three">
  <div class="stat"><span><?= e(date('F Y', strtotime($month . '-01'))) ?></span><strong><?= money($total, $currency) ?></strong><small><?= count($expenses) ?> expense(s)</small></div>
  <div class="stat"><span>Last 30 days</span><strong><?= money($last30, $currency) ?></strong><small>All categories</small></div>
  <div class="stat"><span>Recurring monthly</span><strong><?= money($recurring, $currency) ?></strong><small>Marked recurring</small></div>
</div>

<div class="columns">
  <div class="card">
    <div class="card-head">
      <h3>Ledger</h3>
      <form class="filters flush" method="get" action="<?= url('/expenses') ?>">
        <input type="month" name="month" value="<?= e($month) ?>" data-autosubmit>
      </form>
    </div>
    <?php if (!$expenses): ?><div class="empty">No expenses recorded for this month.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Vendor</th><th class="text-right">Amount</th><?php if ($canDelete): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($expenses as $x): ?>
        <tr>
          <td><small><?= date_fmt($x['expense_date']) ?></small></td>
          <td class="wrap"><b><?= e($x['description']) ?></b><?php if ($x['recurring']): ?> <small class="muted">(recurring)</small><?php endif; ?><?php if ($x['notes']): ?><br><small class="muted"><?= e($x['notes']) ?></small><?php endif; ?></td>
          <td><?= e($x['category']) ?></td>
          <td><?= e($x['vendor'] ?: '—') ?></td>
          <td class="text-right"><?= money($x['amount'], $x['currency']) ?></td>
          <?php if ($canDelete): ?>
          <td class="text-right">
            <form method="post" action="<?= url('/expenses/' . $x['id'] . '/delete') ?>" class="inline" data-confirm="Delete this expense? This cannot be undone.">
              <?= csrf_field() ?>
              <button class="btn small danger" type="submit">Delete</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4" class="text-right"><b>Total</b></td><td class="text-right"><b><?= money($total, $currency) ?></b></td><?php if ($canDelete): ?><td></td><?php endif; ?></tr></tfoot>
    </table></div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h3>Record expense</h3></div>
      <form method="post" action="<?= url('/expenses') ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
          <label class="field"><span>Date *</span><input type="date" name="expense_date" value="<?= e(today()) ?>" required></label>
          <label class="field"><span>Amount (<?= e($currency) ?>) *</span><input type="number" name="amount" min="0.01" step="0.01" required></label>
        </div>
        <div class="form-grid">
          <label class="field"><span>Category *</span>
            <select name="category" required><?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>"><?= e($cat) ?></option><?php endforeach; ?></select>
          </label>
          <label class="field"><span>Vendor</span><input type="text" name="vendor" maxlength="150" placeholder="e.g. Asura, ZICTA, WHMCS"></label>
        </div>
        <label class="field"><span>Description *</span><input type="text" name="description" maxlength="255" required placeholder="e.g. Asura reseller hosting — November"></label>
        <label class="field"><span>Notes</span><textarea name="notes" rows="2" maxlength="1000"></textarea></label>
        <label class="field checkbox"><input type="checkbox" name="recurring" value="1"><span>Recurring monthly expense</span></label>
        <div class="form-actions"><button type="submit" class="btn primary">Record expense</button></div>
      </form>
    </div>

    <?php if ($byCategory): ?>
    <div class="card">
      <div class="card-head"><h3>By category · <?= e(date('M Y', strtotime($month . '-01'))) ?></h3></div>
      <div class="table-scroll"><table>
        <tbody>
        <?php foreach ($byCategory as $cat => $amt): ?>
          <tr><td><?= e($cat) ?></td><td class="text-right"><?= money($amt, $currency) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <?php endif; ?>
  </div>
</div>
