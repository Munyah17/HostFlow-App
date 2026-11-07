<?= view('components.heading', [
    'title' => 'New invoice',
    'subtitle' => 'Emailed to the customer unless saved as a draft',
    'actions' => '<a class="btn ghost" href="' . url('/billing/invoices') . '">Cancel</a>',
], null) ?>

<div class="card">
  <form method="post" action="<?= url('/billing/invoices') ?>">
    <?= csrf_field() ?>
    <div class="form-grid three">
      <label class="field"><span>Customer *</span>
        <select name="customer_id" required onchange="if(this.value)location.href='<?= url('/billing/invoices/create') ?>?customer_id='+this.value">
          <option value="">Select a customer…</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) old('customer_id', $customerId) === (int) $c['id'] ? 'selected' : '' ?>><?= e(($c['business_name'] ?: $c['full_name']) . ' · ' . $c['email']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error('customer_id') ?>
      </label>
      <label class="field"><span>Issue date</span><input type="date" name="issue_date" value="<?= e(old('issue_date', today())) ?>"><?= field_error('issue_date') ?></label>
      <label class="field"><span>Due date *</span><input type="date" name="due_date" value="<?= e(old('due_date', date('Y-m-d', strtotime('+' . (int) setting('invoice_due_days', 7) . ' days')))) ?>" required><?= field_error('due_date') ?></label>
    </div>

    <h4>Line items</h4>
    <?php if (field_error('items')): ?><?= field_error('items') ?><?php endif; ?>
    <div class="table-scroll"><table id="items-table">
      <thead><tr><th class="minw-220">Description</th><?php if ($services): ?><th>Service</th><?php endif; ?><th class="w-90">Qty</th><th class="w-130">Unit price</th><th class="w-40"></th></tr></thead>
      <tbody>
      <?php $oldDesc = old('item_desc', ['']); $oldQty = old('item_qty', ['1']); $oldPrice = old('item_price', ['']); $oldSvc = old('item_service', []);
        $rows = max(3, is_array($oldDesc) ? count($oldDesc) : 1);
        for ($i = 0; $i < $rows; $i++): ?>
        <tr>
          <td><input type="text" name="item_desc[]" value="<?= e(is_array($oldDesc) ? ($oldDesc[$i] ?? '') : '') ?>" placeholder="e.g. Business hosting renewal" maxlength="255" class="w-full"></td>
          <?php if ($services): ?><td><select name="item_service[]"><option value="">—</option><?php foreach ($services as $sv): ?><option value="<?= (int) $sv['id'] ?>" <?= (int) (is_array($oldSvc) ? ($oldSvc[$i] ?? 0) : 0) === (int) $sv['id'] ? 'selected' : '' ?>><?= e($sv['domain']) ?></option><?php endforeach; ?></select></td><?php endif; ?>
          <td><input type="number" name="item_qty[]" value="<?= e(is_array($oldQty) ? ($oldQty[$i] ?? '1') : '1') ?>" min="0.01" step="0.01" class="w-full"></td>
          <td><input type="number" name="item_price[]" value="<?= e(is_array($oldPrice) ? ($oldPrice[$i] ?? '') : '') ?>" min="0" step="0.01" class="w-full"></td>
          <td><button type="button" class="link-btn" onclick="this.closest('tr').remove()">✕</button></td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table></div>
    <div class="form-actions"><button type="button" class="btn ghost small" id="add-item">+ Add item</button></div>

    <div class="form-grid mt-14">
      <label class="field"><span>Discount (<?= e(setting('currency', 'USD')) ?>)</span><input type="number" name="discount" value="<?= e(old('discount')) ?>" min="0" step="0.01"><?= field_error('discount') ?></label>
      <label class="field full"><span>Notes (shown on the invoice)</span><textarea name="notes" maxlength="5000" class="ta-xs"><?= e(old('notes')) ?></textarea><?= field_error('notes') ?></label>
    </div>
    <label class="check"><input type="checkbox" name="draft" value="1" <?= old('draft') ? 'checked' : '' ?>> Save as draft — don't notify the customer yet</label>
    <div class="form-actions"><button type="submit" class="btn primary">Create invoice</button></div>
  </form>
</div>

<script>
document.getElementById('add-item').addEventListener('click', function () {
  var tbody = document.querySelector('#items-table tbody');
  var tr = tbody.rows[0].cloneNode(true);
  tr.querySelectorAll('input').forEach(function (i) { i.value = i.name === 'item_qty[]' ? '1' : ''; });
  tr.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
  tbody.appendChild(tr);
});
</script>
