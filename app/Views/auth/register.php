<h1>Create your account</h1>
<p class="sub">Register to order web and email hosting, pay invoices and get support.</p>
<form method="post" action="<?= url('/register') ?>" novalidate>
  <?= csrf_field() ?>
  <div class="form-grid">
    <label>Full name *<input name="full_name" value="<?= e(old('full_name')) ?>" required autocomplete="name"><?= field_error('full_name') ?></label>
    <label>Business name<input name="business_name" value="<?= e(old('business_name')) ?>" autocomplete="organization"><?= field_error('business_name') ?></label>
    <label>Email address *<input type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email"><?= field_error('email') ?></label>
    <label>Phone *<input type="tel" name="phone" value="<?= e(old('phone')) ?>" required autocomplete="tel" placeholder="+263 77 000 0000"><?= field_error('phone') ?></label>
    <label class="full">Address<input name="address" value="<?= e(old('address')) ?>" autocomplete="street-address"><?= field_error('address') ?></label>
    <label>City<input name="city" value="<?= e(old('city')) ?>" autocomplete="address-level2"></label>
    <label>Country *<input name="country" value="<?= e(old('country', setting('default_country'))) ?>" required autocomplete="country-name"><?= field_error('country') ?></label>
    <label>Password *<input type="password" name="password" required autocomplete="new-password" minlength="<?= (int) setting('min_password_length') ?>"><?= field_error('password') ?><span class="help">At least <?= (int) setting('min_password_length') ?> characters.</span></label>
    <label>Confirm password *<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
  </div>
  <div class="form-actions"><button type="submit" class="btn primary">Create account</button></div>
</form>
<div class="auth-links"><span class="muted">Already have an account?</span><a href="<?= url('/login') ?>">Sign in</a></div>
