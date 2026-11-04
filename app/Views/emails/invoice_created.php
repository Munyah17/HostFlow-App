<p>Hello <?= e($customer_name) ?>,</p>
<p>A new invoice has been issued for your account.</p>
<table class="info">
  <tr><td>Invoice</td><td><b><?= e($invoice_number) ?></b></td></tr>
  <tr><td>Amount due</td><td><b><?= e($total) ?></b></td></tr>
  <tr><td>Due date</td><td><?= e($due_date) ?></td></tr>
</table>
<p><a class="button" href="<?= e($invoice_url) ?>">View and pay invoice</a></p>
