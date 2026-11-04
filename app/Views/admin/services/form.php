<?php
use App\Services\BillingCycle;

$prices = fn (array $plan) => e(json_encode(array_filter([
    'monthly' => $plan['price_monthly'],
    'quarterly' => $plan['price_quarterly'],
    'semiannual' => $plan['price_semiannual'],
    'annual' => $plan['price_annual'],
], fn ($v) => $v !== null)));
?>
<?= view('components.heading', [
    'title' => 'Add service',
    'subtitle' => 'Creates the service plus an invoice — provisioning starts once it is paid',
    'actions' => '<a class="btn ghost" href="' . url('/hosting') . '">Cancel</a>',
], null) ?>

<div class="card" style="max-width:760px">
  <form method="post" action="<?= url('/hosting') ?>" data-plan-select>
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="field"><span>Customer *</span>
        <select name="customer_id" required>
          <option value="">Select a customer…</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) old('customer_id', $customerId) === (int) $c['id'] ? 'selected' : '' ?>><?= e(($c['business_name'] ?: $c['full_name']) . ' · ' . $c['email']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error('customer_id') ?>
      </label>
      <label class="field"><span>Hosting plan *</span>
        <select name="plan_id" required>
          <option value="">Select a plan…</option>
          <?php $lastType = null; foreach ($plans as $plan): ?>
            <?php if ($plan['type'] !== $lastType): $lastType = $plan['type']; ?><optgroup label="<?= $plan['type'] === 'email' ? 'Email hosting' : 'Web hosting' ?>"><?php endif; ?>
            <option value="<?= (int) $plan['id'] ?>" data-prices="<?= $prices($plan) ?>" <?= (int) old('plan_id') === (int) $plan['id'] ? 'selected' : '' ?>><?= e($plan['name']) ?><?= $plan['status'] === 'hidden' ? ' (hidden)' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error('plan_id') ?>
      </label>
      <label class="field"><span>Domain *</span>
        <input type="text" name="domain" value="<?= e(old('domain')) ?>" required placeholder="example.co.zw" maxlength="253">
        <?= field_error('domain') ?>
      </label>
      <label class="field"><span>Billing cycle *</span>
        <select name="billing_cycle" required>
          <?php foreach (BillingCycle::LABELS as $key => $label): ?>
            <option value="<?= $key ?>" data-label="<?= e($label) ?>" <?= old('billing_cycle', 'monthly') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error('billing_cycle') ?>
      </label>
      <label class="field"><span>Custom price (leave blank for plan price: <b data-price-output>—</b>)</span>
        <input type="number" name="price" value="<?= e(old('price')) ?>" min="0" step="0.01" placeholder="Plan price">
        <?= field_error('price') ?>
        <span class="help">The plan's setup fee is added automatically on the invoice.</span>
      </label>
      <label class="field full"><span>Notes (staff only)</span>
        <textarea name="notes" maxlength="5000"><?= e(old('notes')) ?></textarea>
        <?= field_error('notes') ?>
      </label>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn primary">Create order</button>
    </div>
  </form>
</div>
