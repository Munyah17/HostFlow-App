<p>Hello <?= e($customer_name) ?>,</p>
<p>Invoice <b><?= e($invoice_number) ?></b> for <b><?= e($total) ?></b> was due on <?= e($due_date) ?> and is now overdue.</p>
<?php if (!empty($suspend_date)): ?><p>To avoid suspension of your services, please pay before <b><?= e($suspend_date) ?></b>.</p><?php endif; ?>
<p><a class="button" href="<?= e($invoice_url) ?>">Pay now</a></p>
