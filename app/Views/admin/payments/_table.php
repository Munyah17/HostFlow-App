<?php
/** Shared payments table. $payments rows need: reference, number (invoice), full_name/business_name, amount, currency, gateway, method, status, paid_at/created_at, invoice_id. $compact hides the customer column. */
if (!$payments): ?>
  <div class="empty">No payments recorded.</div>
<?php else: ?>
<div class="table-scroll"><table class="<?= !empty($compact) ? 'compact' : '' ?>">
  <thead><tr><th>Reference</th><th>Invoice</th><?php if (empty($compact)): ?><th>Customer</th><?php endif; ?><th class="text-right">Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
  <tbody>
  <?php foreach ($payments as $pm): ?>
    <tr>
      <td><span class="mono"><?= e($pm['reference']) ?></span><?php if ($pm['gateway_reference'] ?? null): ?><br><small class="muted"><?= e(str_limit($pm['gateway_reference'], 30)) ?></small><?php endif; ?></td>
      <td><a href="<?= url('/billing/invoices/view/' . $pm['invoice_id']) ?>"><?= e($pm['number'] ?? '#' . $pm['invoice_id']) ?></a></td>
      <?php if (empty($compact)): ?><td><a href="<?= url('/customers/view/' . $pm['customer_id']) ?>"><?= e(($pm['business_name'] ?? '') ?: ($pm['full_name'] ?? '')) ?></a></td><?php endif; ?>
      <td class="text-right"><?= money($pm['amount'], $pm['currency'] ?? null) ?></td>
      <td><?= e(\App\Services\PaymentService::methodLabel($pm['gateway'], $pm['method'] ?? null)) ?></td>
      <td><?= badge($pm['status']) ?></td>
      <td><small><?= datetime_fmt($pm['paid_at'] ?: $pm['created_at']) ?></small></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
