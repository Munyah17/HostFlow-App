<?= view('components.heading', [
    'title' => 'My profile',
    'subtitle' => 'Contact details and password',
], null) ?>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Contact details</h3></div>
    <form method="post" action="<?= url('/client/profile') ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <label class="field"><span>Full name *</span><input type="text" name="full_name" value="<?= e(old('full_name', $customer['full_name'])) ?>" required maxlength="150"><?= field_error('full_name') ?></label>
        <label class="field"><span>Business name</span><input type="text" name="business_name" value="<?= e(old('business_name', $customer['business_name'])) ?>" maxlength="150"><?= field_error('business_name') ?></label>
        <label class="field"><span>Email *</span><input type="email" name="email" value="<?= e(old('email', $customer['email'])) ?>" required maxlength="190"><?= field_error('email') ?><span class="help">Also your login email.</span></label>
        <label class="field"><span>Phone</span><input type="text" name="phone" value="<?= e(old('phone', $customer['phone'])) ?>" maxlength="40"><?= field_error('phone') ?></label>
        <label class="field full"><span>Address</span><input type="text" name="address" value="<?= e(old('address', $customer['address'])) ?>" maxlength="255"><?= field_error('address') ?></label>
        <label class="field"><span>City</span><input type="text" name="city" value="<?= e(old('city', $customer['city'])) ?>" maxlength="100"><?= field_error('city') ?></label>
        <label class="field"><span>Country *</span><input type="text" name="country" value="<?= e(old('country', $customer['country'])) ?>" required maxlength="100"><?= field_error('country') ?></label>
      </div>
      <div class="form-actions"><button type="submit" class="btn primary">Save details</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Change password</h3></div>
    <?php if (!$user): ?>
      <div class="empty">No portal login is attached to this account.</div>
    <?php else: ?>
    <form method="post" action="<?= url('/client/profile/password') ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Current password *</span><input type="password" name="current_password" required autocomplete="current-password"><?= field_error('current_password') ?></label>
      <label class="field"><span>New password *</span><input type="password" name="password" required autocomplete="new-password"><?= field_error('password') ?><span class="help">Minimum <?= (int) setting('min_password_length', 10) ?> characters.</span></label>
      <label class="field"><span>Repeat new password *</span><input type="password" name="password_confirmation" required autocomplete="new-password"></label>
      <div class="form-actions"><button type="submit" class="btn primary">Change password</button></div>
    </form>
    <?php endif; ?>

    <h4>Sign-in email</h4>
    <p class="small muted"><?= $user ? e($user['email']) . ($user['last_login_at'] ? ' · last sign-in ' . datetime_fmt($user['last_login_at']) : '') : '—' ?></p>
  </div>
</div>
