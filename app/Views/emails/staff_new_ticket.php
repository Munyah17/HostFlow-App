<p>A new support ticket has been opened.</p>
<table class="info">
  <tr><td>Ticket</td><td><?= e($ticket_number) ?></td></tr>
  <tr><td>Customer</td><td><?= e($customer_name) ?></td></tr>
  <tr><td>Subject</td><td><?= e($ticket_subject) ?></td></tr>
  <tr><td>Priority</td><td><?= e(ucfirst((string) ($priority ?? 'normal'))) ?></td></tr>
</table>
<blockquote><?= nl2br(e($message)) ?></blockquote>
<p><a class="button" href="<?= e($ticket_url) ?>">Open ticket</a></p>
