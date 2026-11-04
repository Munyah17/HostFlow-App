<?= view('components.heading', [
    'title' => 'Register a domain',
    'subtitle' => 'Enter the domain you want — we register it as soon as the invoice is paid',
    'actions' => '<a class="btn ghost" href="' . url('/client/domains') . '">Cancel</a>',
], null) ?>

<?php if (!$pricing): ?>
  <div class="card"><div class="empty">No extensions are on sale right now. Please check back soon or <a href="<?= url('/client/support/create') ?>">contact us</a>.</div></div>
<?php else: ?>
<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Your order</h3></div>
    <form method="post" action="<?= url('/client/domains/order') ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Domain *</span>
        <input type="text" name="domain" value="<?= e(old('domain')) ?>" required placeholder="yourdomain.co.zw" maxlength="253">
        <?= field_error('domain') ?>
        <small class="help">The extension sets the price — see the list on the right.</small>
      </label>
      <label class="field"><span>Years *</span>
        <select name="years">
          <?php for ($y = 1; $y <= 10; $y++): ?><option value="<?= $y ?>" <?= (int) old('years', '1') === $y ? 'selected' : '' ?>><?= $y ?> year<?= $y > 1 ? 's' : '' ?></option><?php endfor; ?>
        </select>
        <?= field_error('years') ?>
      </label>
      <label class="check"><input type="checkbox" checked disabled> Renewal invoices are generated automatically before expiry</label>
      <div class="form-actions"><button type="submit" class="btn primary">Continue to payment</button></div>
    </form>
  </div>
  <div class="card">
    <div class="card-head"><h3>Extensions & prices</h3></div>
    <div class="table-scroll"><table>
      <thead><tr><th>Extension</th><th class="text-right">Register /yr</th><th class="text-right">Renew /yr</th></tr></thead>
      <tbody>
      <?php foreach ($pricing as $t): ?>
        <tr><td><span class="mono"><?= e($t['tld']) ?></span></td><td class="text-right"><?= money($t['register_price']) ?></td><td class="text-right"><?= money($t['renew_price']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>
