<p>Hello <?= e($customer_name) ?>,</p>
<p>There is a new reply on your support ticket <b><?= e($ticket_number) ?></b> — <?= e($ticket_subject) ?>.</p>
<blockquote><?= nl2br(e($message)) ?></blockquote>
<p><a class="button" href="<?= e($ticket_url) ?>">View ticket</a></p>
