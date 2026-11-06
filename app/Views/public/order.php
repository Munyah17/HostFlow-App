<?php
use App\Services\BillingCycle;
$prices = array_filter([
    'monthly' => $plan['price_monthly'],
    'quarterly' => $plan['price_quarterly'],
    'semiannual' => $plan['price_semiannual'],
    'annual' => $plan['price_annual'],
], fn ($v) => $v !== null);
$defaultCycle = array_key_exists((string) ($plan['default_cycle'] ?? ''), $prices) ? (string) $plan['default_cycle'] : (string) array_key_first($prices);
$min = max(8, (int) setting('min_password_length', 10));
?>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Order <?= e($plan['name']) ?></h3><span class="muted small"><?= $plan['type'] === 'email' ? 'Email hosting' : 'Web hosting' ?></span></div>
    <form method="post" action="<?= url('/order/' . (int) $plan['id']) ?>">
      <?= csrf_field() ?>
      <h4 style="margin:0 0 10px">Hosting details</h4>
      <label class="field"><span>Domain *</span><input type="text" name="domain" value="<?= e(old('domain')) ?>" required placeholder="yourdomain.co.zw" maxlength="253"><?= field_error('domain') ?><span class="help">The domain your hosting will be attached to.</span></label>
      <label class="field"><span>Billing cycle *</span>
        <select name="billing_cycle" required>
          <?php foreach ($prices as $cycle => $price): ?><option value="<?= e($cycle) ?>" <?= old('billing_cycle', $defaultCycle) === $cycle ? 'selected' : '' ?>><?= e(BillingCycle::LABELS[$cycle] ?? $cycle) ?> — <?= money($price) ?></option><?php endforeach; ?>
        </select>
        <?= field_error('billing_cycle') ?>
      </label>
      <?php if ((float) $plan['setup_fee'] > 0): ?><p class="small muted">One-time setup fee: <?= money($plan['setup_fee']) ?></p><?php endif; ?>

      <h4 style="margin:18px 0 10px">Your account</h4>
      <div class="form-grid">
        <label class="field"><span>Full name *</span><input type="text" name="full_name" value="<?= e(old('full_name')) ?>" required maxlength="150"><?= field_error('full_name') ?></label>
        <label class="field"><span>Business name</span><input type="text" name="business_name" value="<?= e(old('business_name')) ?>" maxlength="150"></label>
      </div>
      <div class="form-grid">
        <label class="field"><span>Email *</span><input type="email" name="email" value="<?= e(old('email')) ?>" required maxlength="190"><?= field_error('email') ?></label>
        <label class="field"><span>Phone *</span><input type="tel" name="phone" value="<?= e(old('phone')) ?>" required maxlength="40" placeholder="0772 123 456"><?= field_error('phone') ?></label>
      </div>
      <label class="field"><span>Country *</span><input type="text" name="country" value="<?= e(old('country', setting('default_country', 'Zimbabwe'))) ?>" required maxlength="100"><?= field_error('country') ?></label>
      <div class="form-grid">
        <label class="field"><span>Password *</span><input type="password" name="password" required minlength="<?= $min ?>" autocomplete="new-password"><?= field_error('password') ?><span class="help">At least <?= $min ?> characters — for your client area sign-in.</span></label>
        <label class="field"><span>Confirm password *</span><input type="password" name="password_confirmation" required minlength="<?= $min ?>" autocomplete="new-password"></label>
      </div>
      <div class="form-actions"><button type="submit" class="btn primary">Create account &amp; continue to payment</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Your plan</h3></div>
    <div class="plan" style="border:0;padding:0">
      <div class="plan-top"><div><h4 style="margin:0"><?= e($plan['name']) ?></h4></div></div>
      <?php if ($plan['description']): ?><p class="small muted" style="margin:8px 0"><?= e($plan['description']) ?></p><?php endif; ?>
      <ul>
        <?php if ($plan['storage_gb'] !== null): ?><li><?= e(rtrim(rtrim(number_format((float) $plan['storage_gb'], 2), '0'), '.')) ?> GB storage</li><?php endif; ?>
        <?php if ($plan['bandwidth_gb'] !== null): ?><li><?= e(rtrim(rtrim(number_format((float) $plan['bandwidth_gb'], 2), '0'), '.')) ?> GB bandwidth</li><?php endif; ?>
        <?php if ($plan['email_accounts'] !== null): ?><li><?= (int) $plan['email_accounts'] ?> email account(s)</li><?php endif; ?>
        <?php foreach (array_slice(array_filter(array_map('trim', explode("\n", (string) $plan['features']))), 0, 6) as $feat): ?><li><?= e($feat) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <h4 style="margin:18px 0 8px">What happens next</h4>
    <ul class="small" style="margin:0;padding-left:18px;color:var(--muted)">
      <li>Your account and invoice are created instantly.</li>
      <li>Pay online — your cPanel account is activated automatically.</li>
      <li>Login details and your receipt arrive by email.</li>
    </ul>
    <p class="small" style="margin-top:16px">Already a customer? <a href="<?= url('/login') ?>">Sign in</a> and order from your client area.</p>
  </div>
</div>
