<p>Hello <?= e($customer_name) ?>,</p>
<p>Your domain <b><?= e($domain) ?></b> has been registered and is now active.</p>
<table class="info">
  <tr><td>Domain</td><td><?= e($domain) ?></td></tr>
  <tr><td>Expiry date</td><td><?= e($expiry_date) ?></td></tr>
</table>
<p>You can manage nameservers and renewals from your client portal.</p>
<p><a class="button" href="<?= e($domain_url) ?>">Manage domain</a></p>
