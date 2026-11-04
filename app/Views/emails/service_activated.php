<p>Hello <?= e($customer_name) ?>,</p>
<p>Your <?= e($plan) ?> service for <b><?= e($domain) ?></b> is now active.</p>
<table class="info">
  <tr><td>Domain</td><td><?= e($domain) ?></td></tr>
  <tr><td>Control panel username</td><td><?= e($username) ?></td></tr>
  <?php if (!empty($nameservers)): ?><tr><td>Nameservers</td><td><?= e($nameservers) ?></td></tr><?php endif; ?>
</table>
<p>Sign in to the client portal and choose <b>Open control panel</b> to access cPanel securely without a password. From cPanel you can upload your website, create email accounts and set a control panel password if you need one.</p>
<p><a class="button" href="<?= e($service_url) ?>">Manage service</a></p>
