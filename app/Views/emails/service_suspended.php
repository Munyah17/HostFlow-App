<p>Hello <?= e($customer_name) ?>,</p>
<p>Your service for <b><?= e($domain) ?></b> has been suspended.</p>
<p>Reason: <?= e($reason) ?></p>
<p>If this is due to an unpaid invoice, the service will be reactivated automatically once payment is confirmed.</p>
<p><a class="button" href="<?= e($portal_url) ?>">Open client portal</a></p>
