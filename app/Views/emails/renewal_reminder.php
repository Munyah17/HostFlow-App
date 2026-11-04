<p>Hello <?= e($customer_name) ?>,</p>
<p>Your service for <b><?= e($domain) ?></b> is due for renewal on <b><?= e($due_date) ?></b>.</p>
<table class="info">
  <tr><td>Invoice</td><td><?= e($invoice_number) ?></td></tr>
  <tr><td>Amount</td><td><b><?= e($total) ?></b></td></tr>
</table>
<p>Please pay before the due date to avoid any interruption.</p>
<p><a class="button" href="<?= e($invoice_url) ?>">Pay now</a></p>
