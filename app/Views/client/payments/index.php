<?= view('components.heading', ['title' => 'Payments', 'subtitle' => 'Your payment history'], null) ?>

<div class="card">
  <?php if (!$payments): ?>
    <div class="empty">No payments yet.</div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Reference</th><th>Invoice</th><th class="text-right">Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $pm): ?>
      <tr>
        <td><span class="mono"><?= e($pm['reference']) ?></span></td>
        <td><a href="<?= url('/client/invoices/' . $pm['invoice_id']) ?>"><?= e($pm['number']) ?></a></td>
        <td class="text-right"><?= money($pm['amount'], $pm['currency']) ?></td>
        <td><?= e(\App\Services\PaymentService::methodLabel($pm['gateway'], $pm['method'])) ?></td>
        <td><?= badge($pm['status']) ?></td>
        <td><small><?= datetime_fmt($pm['paid_at'] ?: $pm['created_at']) ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
