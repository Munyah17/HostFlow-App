<?= view('components.heading', [
    'title' => 'My invoices',
    'subtitle' => (float) $stats['balance'] > 0 ? money($stats['balance']) . ' outstanding' : 'Nothing owing',
], null) ?>

<div class="stats three">
  <div class="stat"><span>Balance due</span><strong><?= money($stats['balance']) ?></strong><small>Unpaid invoices</small></div>
  <div class="stat"><a href="<?= url('/client/invoices', ['status' => 'overdue']) ?>"><span>Overdue</span><strong><?= (int) $stats['overdue_count'] ?></strong><small>Invoice(s) past due</small></a></div>
  <div class="stat"><span>Paid this month</span><strong><?= money($stats['paid_month']) ?></strong><small>Total received</small></div>
</div>

<div class="card">
  <form class="filters" method="get" action="<?= url('/client/invoices') ?>">
    <select name="status" data-autosubmit>
      <option value="">All statuses</option>
      <?php foreach (['pending', 'overdue', 'paid', 'cancelled'] as $st): ?>
        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn ghost" type="submit">Filter</button>
  </form>

  <?php if (!$invoices): ?>
    <div class="empty">No invoices found.<?= $status ? ' <a href="' . url('/client/invoices') . '">Clear filter</a>.' : '' ?></div>
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
