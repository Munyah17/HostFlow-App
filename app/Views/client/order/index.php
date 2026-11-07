<?php
use App\Services\BillingCycle;

$prices = fn (array $plan) => e(json_encode(array_filter([
    'monthly' => $plan['price_monthly'],
    'quarterly' => $plan['price_quarterly'],
    'semiannual' => $plan['price_semiannual'],
    'annual' => $plan['price_annual'],
], fn ($v) => $v !== null)));
$web = array_filter($plans, fn ($p) => $p['type'] === 'web');
$email = array_filter($plans, fn ($p) => $p['type'] === 'email');
?>
<?= view('components.heading', [
    'title' => 'Order hosting',
    'subtitle' => 'Pick a plan, enter your domain — your account is set up as soon as the invoice is paid',
], null) ?>

<?php if (!$plans): ?>
  <div class="card"><div class="empty">No plans are available right now. Please check back soon or <a href="<?= url('/client/support/create') ?>">contact us</a>.</div></div>
<?php else: ?>
<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Your order</h3></div>
    <form method="post" action="<?= url('/client/order') ?>" data-plan-select>
      <?= csrf_field() ?>
      <label class="field"><span>Plan *</span>
        <select name="plan_id" required>
          <option value="">Select a plan…</option>
          <?php if ($web): ?><optgroup label="Web hosting"><?php foreach ($web as $p): ?><option value="<?= (int) $p['id'] ?>" data-prices="<?= $prices($p) ?>" <?= (int) old('plan_id') === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
          <?php if ($email): ?><optgroup label="Email hosting"><?php foreach ($email as $p): ?><option value="<?= (int) $p['id'] ?>" data-prices="<?= $prices($p) ?>" <?= (int) old('plan_id') === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
        </select>
        <?= field_error('plan_id') ?>
      </label>
      <label class="field"><span>Domain *</span><input type="text" name="domain" value="<?= e(old('domain')) ?>" required placeholder="yourdomain.co.zw" maxlength="253"><?= field_error('domain') ?></label>
      <label class="field"><span>Billing cycle *</span>
        <select name="billing_cycle">
          <?php foreach (BillingCycle::LABELS as $key => $label): ?><option value="<?= $key ?>" data-label="<?= e($label) ?>" <?= old('billing_cycle', 'monthly') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <?= field_error('billing_cycle') ?>
      </label>
      <p class="price-lg"><strong data-price-output>—</strong></p>
      <div class="form-actions"><button type="submit" class="btn primary">Continue to payment</button></div>
    </form>
  </div>
  <div class="card">
    <div class="card-head"><h3>Plans</h3></div>
    <div class="plan-grid one">
      <?php foreach ($plans as $p): ?>
        <div class="plan">
          <div class="plan-top"><div><h4><?= e($p['name']) ?></h4><small class="muted"><?= $p['type'] === 'email' ? 'Email hosting' : 'Web hosting' ?></small></div><strong><?= $p['price_monthly'] !== null ? money($p['price_monthly']) . '<small class="muted">/mo</small>' : '' ?></strong></div>
          <p class="small muted my-8"><?= e($p['description'] ?: '') ?></p>
          <ul>
            <?php if ($p['storage_gb'] !== null): ?><li><?= e(rtrim(rtrim(number_format((float) $p['storage_gb'], 2), '0'), '.')) ?> GB storage</li><?php endif; ?>
            <?php if ($p['email_accounts'] !== null): ?><li><?= (int) $p['email_accounts'] ?> email account(s)</li><?php endif; ?>
            <?php foreach (array_slice(array_filter(array_map('trim', explode("\n", (string) $p['features']))), 0, 4) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>
