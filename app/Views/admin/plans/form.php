<?php
use App\Services\BillingCycle;

$editing = $plan !== null;
$val = fn (string $k) => e(old($k, $plan[$k] ?? ''));
$field = function (string $name, string $label, string $type = 'text', string $extra = '') use ($val) {
    return '<label class="field"><span>' . $label . '</span><input type="' . $type . '" name="' . $name . '" value="' . $val($name) . '" ' . $extra . '>' . field_error($name) . '</label>';
};
?>
<?= view('components.heading', [
    'title' => $editing ? 'Edit plan' : 'Add plan',
    'subtitle' => $editing ? $plan['name'] : 'A sellable hosting package',
    'actions' => '<a class="btn ghost" href="' . url('/hosting/plans') . '">Cancel</a>',
], null) ?>

<div class="card card-wide">
  <form method="post" action="<?= url($editing ? '/hosting/plans/update/' . $plan['id'] : '/hosting/plans') ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="field"><span>Name *</span><input type="text" name="name" value="<?= $val('name') ?>" required maxlength="120"><?= field_error('name') ?></label>
      <label class="field"><span>Type *</span>
        <select name="type">
          <option value="web" <?= old('type', $plan['type'] ?? 'web') === 'web' ? 'selected' : '' ?>>Web hosting</option>
          <option value="email" <?= old('type', $plan['type'] ?? '') === 'email' ? 'selected' : '' ?>>Email hosting</option>
        </select>
        <?= field_error('type') ?>
      </label>
      <label class="field full"><span>Description</span><input type="text" name="description" value="<?= $val('description') ?>" maxlength="2000"><?= field_error('description') ?></label>
    </div>

    <fieldset><legend>Pricing (<?= e(setting('currency', 'USD')) ?>)</legend>
      <div class="form-grid three">
        <?= $field('price_monthly', 'Monthly', 'number', 'min="0" step="0.01"') ?>
        <?= $field('price_quarterly', 'Quarterly', 'number', 'min="0" step="0.01"') ?>
        <?= $field('price_semiannual', 'Semi-annual', 'number', 'min="0" step="0.01"') ?>
        <?= $field('price_annual', 'Annual', 'number', 'min="0" step="0.01"') ?>
        <?= $field('setup_fee', 'Setup fee', 'number', 'min="0" step="0.01"') ?>
        <label class="field"><span>Default cycle *</span>
          <select name="default_cycle">
            <?php foreach (BillingCycle::LABELS as $key => $l): ?><option value="<?= $key ?>" <?= old('default_cycle', $plan['default_cycle'] ?? 'monthly') === $key ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
          </select>
          <span class="help">Leave a price blank to not offer that cycle.</span>
        </label>
      </div>
    </fieldset>

    <fieldset><legend>Limits</legend>
      <div class="form-grid three">
        <?= $field('storage_gb', 'Storage (GB)', 'number', 'min="0" step="0.01"') ?>
        <?= $field('bandwidth_gb', 'Bandwidth (GB)', 'number', 'min="0" step="0.01"') ?>
        <?= $field('domains_allowed', 'Domains allowed', 'number', 'min="0" step="1"') ?>
        <?= $field('email_accounts', 'Email accounts', 'number', 'min="0" step="1"') ?>
        <?= $field('databases_allowed', 'Databases', 'number', 'min="0" step="1"') ?>
        <?= $field('sort_order', 'Sort order', 'number', 'min="0" step="1"') ?>
      </div>
      <label class="field"><span>Feature list (one per line, shown to clients)</span><textarea name="features" maxlength="5000" class="ta-sm"><?= $val('features') ?></textarea><?= field_error('features') ?></label>
    </fieldset>

    <fieldset><legend>Provisioning</legend>
      <div class="form-grid">
        <label class="field"><span>Hosting provider</span>
          <select name="provider_id">
            <option value="">None — provision manually</option>
            <?php foreach ($providers as $pr): ?><option value="<?= (int) $pr['id'] ?>" <?= (int) old('provider_id', $plan['provider_id'] ?? 0) === (int) $pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option><?php endforeach; ?>
          </select>
          <?= field_error('provider_id') ?>
        </label>
        <label class="field"><span>Provider package</span>
          <input type="text" name="provider_package" value="<?= $val('provider_package') ?>" maxlength="150" list="whm-packages" placeholder="e.g. globalsp_Business">
          <datalist id="whm-packages">
            <?php foreach ($packageNames ?? [] as $pn): ?><option value="<?= e($pn) ?>"><?php endforeach; ?>
          </datalist>
          <span class="help">Pick a WHM package or type a custom name. <a href="<?= url('/hosting/packages') ?>">Manage server packages</a>.</span>
          <?= field_error('provider_package') ?>
        </label>
      </div>
      <label class="field"><span>Status *</span>
        <select name="status">
          <?php foreach (['active' => 'Active — clients can order', 'hidden' => 'Hidden — staff only', 'retired' => 'Retired — existing services only'] as $st => $l): ?>
            <option value="<?= $st ?>" <?= old('status', $plan['status'] ?? 'hidden') === $st ? 'selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error('status') ?>
      </label>
    </fieldset>

    <div class="form-actions"><button type="submit" class="btn primary"><?= $editing ? 'Save changes' : 'Create plan' ?></button></div>
  </form>
</div>
