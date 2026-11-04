<p>Hello <?= e($name ?? '') ?>,</p>
<p>We received a request to reset your password. This link is valid for 60 minutes and can be used once.</p>
<p><a class="button" href="<?= e($reset_url) ?>">Reset password</a></p>
<p>If you did not request this, you can ignore this email — your password will not change.</p>
