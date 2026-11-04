<?= view('components.heading', [
    'title' => 'My invoices',
    'subtitle' => (float) $balance > 0 ? money($balance) . ' outstanding' : 'Nothing owing',
], null) ?>

<div class="card">
  <?php if (!$invoices): ?>
    <div class="empty">No invoices yet.</div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Invoice</th><th>Issued</th><th>Due</th><th class="text-right">Total</th><th class="text-right">Balance</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($invoices as $i): $b = max(0, (float) $i['total'] - (float) $i['amount_paid']); ?>
      <tr>
        <td><a href="<?= url('/client/invoices/' . $i['id']) ?>"><?= e($i['number']) ?></a></td>
        <td><?= date_fmt($i['issue_date']) ?></td>
        <td><?= date_fmt($i['due_date']) ?></td>
        <td class="text-right"><?= money($i['total'], $i['currency']) ?></td>
        <td class="text-right"><?= $b > 0 ? money($b, $i['currency']) : '<span class="muted">—</span>' ?></td>
        <td><?= badge($i['status']) ?></td>
        <td class="text-right"><?= $b > 0 && in_array($i['status'], ['pending', 'overdue'], true) ? '<a class="btn small primary" href="' . url('/client/invoices/' . $i['id']) . '">Pay</a>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
