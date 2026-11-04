<p>Hello <?= e($customer_name) ?>,</p>
<p>Your domain <b><?= e($domain) ?></b> expired on <b><?= e($expiry_date) ?></b>.</p>
<p>Websites and email on this domain will stop resolving. You can still renew it — after the registrar's grace period it may be released publicly.</p>
<p><a class="button" href="<?= e($domain_url) ?>">Renew now</a></p>
