<?php
$done = in_array($payment['status'], ['completed', 'failed', 'cancelled'], true);
?>
<?= view('components.heading', [
    'title' => 'Payment ' . $payment['reference'],
    'subtitle' => 'Invoice ' . ($invoice['number'] ?? ''),
], null) ?>

<div class="card card-sm status-hero">
  <?php if ($payment['status'] === 'completed'): ?>
    <div class="status-icon ok">✓</div>
    <h3>Payment received</h3>
    <p class="muted">Thank you — your payment of <?= money($payment['amount'], $payment['currency']) ?> was successful.</p>
    <a class="btn primary" href="<?= url('/client/invoices/' . $payment['invoice_id']) ?>">View invoice</a>
  <?php elseif ($payment['status'] === 'failed' || $payment['status'] === 'cancelled'): ?>
    <div class="status-icon fail">✕</div>
    <h3>Payment <?= e(status_label($payment['status'])) ?></h3>
    <p class="muted">No money was taken. You can try again from the invoice.</p>
    <a class="btn primary" href="<?= url('/client/invoices/' . $payment['invoice_id']) ?>">Back to invoice</a>
  <?php else: ?>
    <div class="status-icon pending">…</div>
    <h3>Confirming your payment</h3>
    <p class="muted" data-poll-message>Checking with the payment provider — this usually takes a few seconds.</p>
    <a class="btn ghost" href="<?= url('/client/invoices/' . $payment['invoice_id']) ?>">Skip — I'll check later</a>
  <?php endif; ?>
</div>

<?php if (!$done): ?>
<div data-poll data-poll-status="<?= e(url('/client/payments/' . $payment['reference'] . '/status')) ?>" data-poll-url="<?= e(url('/client/payments/' . $payment['reference'] . '/return')) ?>" data-poll-done="<?= e(url('/client/invoices/' . $payment['invoice_id'])) ?>"></div>
<?php endif; ?>
