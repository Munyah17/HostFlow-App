<?php $statuses = ['draft', 'pending', 'overdue', 'paid', 'cancelled']; ?>
<?= view('components.heading', [
    'title' => 'Invoices',
    'subtitle' => money($stats['open_amount']) . ' outstanding',
    'actions' => '<a class="btn primary" href="' . url('/billing/invoices/create') . '">+ New invoice</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><a href="<?= url('/billing/invoices', ['status' => 'pending']) ?>"><span>Unpaid</span><strong><?= money($stats['open_amount']) ?></strong><small><?= (int) $stats['open_count'] ?> invoice(s)</small></a></div>
  <div class="stat"><a href="<?= url('/billing/invoices', ['status' => 'overdue']) ?>"><span>Overdue</span><strong><?= money($stats['overdue_amount']) ?></strong><small><?= (int) $stats['overdue_count'] ?> invoice(s)</small></a></div>
  <div class="stat"><span>Paid this month</span><strong><?= money($stats['paid_month']) ?></strong><small><?= (int) $stats['drafts'] ?> draft(s)</small></div>
</div>

<div class="card">
  <form class="filters" method="get" action="<?= url('/billing/invoices') ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search number, customer or email…">
    <select name="status" data-autosubmit>
      <option value="">All statuses</option>
      <?php foreach ($statuses as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option><?php endforeach; ?>
    </select>
    <button class="btn ghost" type="submit">Search</button>
  </form>

  <?php if (!$invoices): ?>
    <div class="empty">No invoices found.<?= $q || $status ? ' <a href="' . url('/billing/invoices') . '">Clear filters</a>.' : ' <a href="' . url('/billing/invoices/create') . '">Create the first invoice</a>.' ?></div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Invoice</th><th>Customer</th><th>Issued</th><th>Due</th><th class="text-right">Total</th><th class="text-right">Balance</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($invoices as $i): ?>
      <tr>
        <td><a href="<?= url('/billing/invoices/view/' . $i['id']) ?>"><?= e($i['number']) ?></a></td>
        <td><a href="<?= url('/customers/view/' . $i['customer_id']) ?>"><?= e($i['business_name'] ?: $i['full_name']) ?></a></td>
        <td><?= date_fmt($i['issue_date']) ?></td>
        <td><?= date_fmt($i['due_date']) ?></td>
        <td class="text-right"><?= money($i['total'], $i['currency']) ?></td>
        <td class="text-right"><?= ($b = max(0, $i['total'] - $i['amount_paid'])) > 0 ? money($b, $i['currency']) : '<span class="muted">—</span>' ?></td>
        <td><?= badge($i['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= view('components.pagination', ['p' => $p], null) ?>
  <?php endif; ?>
</div>
