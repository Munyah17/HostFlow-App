<p>Hello <?= e($customer_name) ?>,</p>
<p>Your domain <b><?= e($domain) ?></b> has been renewed.</p>
<table class="info">
  <tr><td>Domain</td><td><?= e($domain) ?></td></tr>
  <tr><td>New expiry date</td><td><?= e($expiry_date) ?></td></tr>
</table>
<p><a class="button" href="<?= e($domain_url) ?>">Manage domain</a></p>
