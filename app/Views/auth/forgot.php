<h1>Forgot password</h1>
<p class="sub">Enter your email address and we will send you a link to reset your password.</p>
<form method="post" action="<?= url('/forgot-password') ?>">
  <?= csrf_field() ?>
  <label class="field">Email address
    <input type="email" name="email" required autocomplete="email" autofocus>
  </label>
  <button type="submit" class="btn primary">Send reset link</button>
</form>
<div class="auth-links"><a href="<?= url('/login') ?>">Back to sign in</a></div>
