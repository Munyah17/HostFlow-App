<h1>Sign in</h1>
<p class="sub">Access your <?= e(setting('business_name')) ?> account.</p>
<form method="post" action="<?= url('/login') ?>" novalidate>
  <?= csrf_field() ?>
  <label class="field">Email address
    <input type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="username" autofocus>
    <?= field_error('email') ?>
  </label>
  <label class="field">Password
    <input type="password" name="password" required autocomplete="current-password">
  </label>
  <button type="submit" class="btn primary">Sign in</button>
</form>
<div class="auth-links">
  <a href="<?= url('/forgot-password') ?>">Forgot your password?</a>
  <?php if (\App\Services\Settings::bool('allow_registration')): ?><a href="<?= url('/register') ?>">Create an account</a><?php endif; ?>
</div>
