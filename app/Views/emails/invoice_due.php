<p>Hello <?= e($customer_name) ?>,</p>
<p>Invoice <b><?= e($invoice_number) ?></b> for <b><?= e($total) ?></b> is due today.</p>
<p><a class="button" href="<?= e($invoice_url) ?>">Pay now</a></p>
