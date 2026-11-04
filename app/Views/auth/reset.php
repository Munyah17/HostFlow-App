<h1>Choose a new password</h1>
<p class="sub">Use at least <?= (int) setting('min_password_length') ?> characters.</p>
<form method="post" action="<?= url('/reset-password/' . $token) ?>">
  <?= csrf_field() ?>
  <label class="field">New password
    <input type="password" name="password" required autocomplete="new-password" autofocus>
    <?= field_error('password') ?>
  </label>
  <label class="field">Confirm new password
    <input type="password" name="password_confirmation" required autocomplete="new-password">
  </label>
  <button type="submit" class="btn primary">Reset password</button>
</form>
