<p>Hello <?= e($customer_name) ?>,</p>
<p>Thank you — we have received your payment.</p>
<table class="info">
  <tr><td>Invoice</td><td><b><?= e($invoice_number) ?></b></td></tr>
  <tr><td>Amount</td><td><b><?= e($amount) ?></b></td></tr>
  <tr><td>Method</td><td><?= e($method) ?></td></tr>
  <tr><td>Reference</td><td><?= e($reference) ?></td></tr>
</table>
<p>Your receipt is available in the client portal.</p>
<p><a class="button" href="<?= e($invoice_url) ?>">View receipt</a></p>
