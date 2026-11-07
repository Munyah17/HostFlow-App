<?= view('components.heading', [
    'title' => 'Add domain',
    'subtitle' => 'Create a domain order (invoice) or record a domain already registered',
    'actions' => '<a class="btn ghost" href="' . url('/domains') . '">Cancel</a>',
], null) ?>

<div class="card card-narrow">
  <form method="post" action="<?= url('/domains') ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="field"><span>Customer *</span>
        <select name="customer_id" required>
          <option value="">Select a customer…</option>
          <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) old('customer_id', $customerId) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['business_name'] ?: $c['full_name']) ?> — <?= e($c['email']) ?></option><?php endforeach; ?>
        </select>
        <?= field_error('customer_id') ?>
      </label>
      <label class="field"><span>Domain *</span><input type="text" name="domain" value="<?= e(old('domain')) ?>" required placeholder="example.co.zw" maxlength="253"><?= field_error('domain') ?></label>
      <label class="field"><span>Linked hosting service</span>
        <select name="service_id">
          <option value="">None</option>
          <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['domain']) ?> — <?= e($s['business_name'] ?: $s['full_name']) ?></option><?php endforeach; ?>
        </select>
        <?= field_error('service_id') ?>
      </label>
      <label class="field"><span>Years *</span><input type="number" name="years" value="<?= e(old('years', '1')) ?>" min="1" max="10" required><?= field_error('years') ?></label>
    </div>
    <?php if ($pricing): ?>
      <p class="small muted">Extensions on sale: <?php foreach ($pricing as $t): ?><span class="mono"><?= e($t['tld']) ?></span> <?= money($t['register_price']) ?><?php endforeach; ?> — manage under <a href="<?= url('/domains/pricing') ?>">Pricing</a>.</p>
    <?php endif; ?>

    <h4>Billing</h4>
    <div class="form-grid">
      <label class="field"><span>Registration price (leave empty for TLD price)</span><input type="number" name="price" value="<?= e(old('price')) ?>" min="0" step="0.01"><?= field_error('price') ?></label>
      <label class="field"><span>Renewal price / year</span><input type="number" name="renewal_price" value="<?= e(old('renewal_price')) ?>" min="0" step="0.01"><?= field_error('renewal_price') ?></label>
    </div>
    <label class="check"><input type="checkbox" name="auto_renew" value="1" <?= old('auto_renew', '1') ? 'checked' : '' ?>> Invoice renewals automatically before expiry</label>
    <label class="field"><span>Notes (staff only)</span><textarea name="notes" maxlength="5000"><?= e(old('notes')) ?></textarea></label>

    <h4>Already registered?</h4>
    <label class="check"><input type="checkbox" name="already_registered" value="1" <?= old('already_registered') ? 'checked' : '' ?> data-toggle="registered-fields"> This domain is already registered — skip the invoice</label>
    <div class="form-grid" data-toggle-target="registered-fields" <?= old('already_registered') ? '' : 'hidden' ?>>
      <label class="field"><span>Registration date</span><input type="date" name="registered_at" value="<?= e(old('registered_at', today())) ?>"><?= field_error('registered_at') ?></label>
      <label class="field"><span>Expiry date</span><input type="date" name="expiry_date" value="<?= e(old('expiry_date')) ?>"><?= field_error('expiry_date') ?></label>
      <label class="field"><span>Registrar</span><input type="text" name="registrar" value="<?= e(old('registrar')) ?>" maxlength="120"><?= field_error('registrar') ?></label>
      <label class="field"><span>Registrar reference</span><input type="text" name="registrar_reference" value="<?= e(old('registrar_reference')) ?>" maxlength="190"></label>
      <label class="field full"><span>Nameservers (comma-separated)</span><input type="text" name="nameservers" value="<?= e(old('nameservers')) ?>" maxlength="500"></label>
    </div>

    <div class="form-actions"><button type="submit" class="btn primary">Save</button></div>
  </form>
</div>
