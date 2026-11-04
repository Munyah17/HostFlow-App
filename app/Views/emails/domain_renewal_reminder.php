<p>Hello <?= e($customer_name) ?>,</p>
<p>Your domain <b><?= e($domain) ?></b> expires on <b><?= e($expiry_date) ?></b>.</p>
<?php if (!empty($invoice_number)): ?>
<table class="info">
  <tr><td>Invoice</td><td><?= e($invoice_number) ?></td></tr>
  <tr><td>Amount</td><td><b><?= e($total) ?></b></td></tr>
</table>
<?php endif; ?>
<p>Renew before the expiry date to keep your website and email running. Expired domains can be released and registered by someone else.</p>
<p><a class="button" href="<?= e($domain_url) ?>">Renew domain</a></p>
