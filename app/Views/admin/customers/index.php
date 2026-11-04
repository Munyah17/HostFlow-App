<?= view('components.heading', [
    'title' => 'Customers',
    'subtitle' => (int) $stats['total'] . ' customers · ' . (int) $stats['active'] . ' active',
    'actions' => '<a class="btn primary" href="' . url('/customers/create') . '">+ Add customer</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><a href="<?= url('/customers') ?>"><span>Total customers</span><strong><?= (int) $stats['total'] ?></strong><small>+<?= (int) $stats['new30'] ?> in the last 30 days</small></a></div>
  <div class="stat"><a href="<?= url('/customers', ['balance' => 'outstanding']) ?>"><span>Outstanding balances</span><strong><?= money($stats['outstanding']) ?></strong><small>Unpaid invoices</small></a></div>
  <div class="stat"><a href="<?= url('/customers', ['balance' => 'overdue']) ?>"><span>Overdue accounts</span><strong><?= (int) db()->fetchColumn("SELECT COUNT(DISTINCT customer_id) FROM invoices WHERE status = 'overdue'") ?></strong><small>With overdue invoices</small></a></div>
</div>

<div class="card">
  <form class="filters" method="get" action="<?= url('/customers') ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, business, email, phone or domain…">
    <select name="status" data-autosubmit>
      <option value="">All statuses</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
    </select>
    <select name="balance" data-autosubmit>
      <option value="">Any balance</option>
      <option value="outstanding" <?= $balance === 'outstanding' ? 'selected' : '' ?>>Has outstanding balance</option>
      <option value="overdue" <?= $balance === 'overdue' ? 'selected' : '' ?>>Has overdue invoices</option>
    </select>
    <button class="btn ghost" type="submit">Search</button>
  </form>

  <?php if (!$customers): ?>
    <div class="empty">No customers found.<?= $q || $status || $balance ? ' <a href="' . url('/customers') . '">Clear filters</a>.' : ' <a href="' . url('/customers/create') . '">Add the first customer</a>.' ?></div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Customer</th><th>Contact</th><th>Services</th><th>Domains</th><th class="text-right">Balance</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($customers as $c): ?>
      <tr>
        <td><a href="<?= url('/customers/view/' . $c['id']) ?>"><?= e($c['business_name'] ?: $c['full_name']) ?></a><?php if ($c['business_name']): ?><br><small class="muted"><?= e($c['full_name']) ?></small><?php endif; ?></td>
        <td><?= e($c['email']) ?><?php if ($c['phone']): ?><br><small class="muted"><?= e($c['phone']) ?></small><?php endif; ?></td>
        <td><?= (int) $c['services'] ?></td>
        <td class="wrap"><small><?= e(str_limit($c['domains'] ?? '', 60)) ?: '—' ?></small></td>
        <td class="text-right"><?= (float) $c['balance'] > 0 ? money($c['balance']) . ((int) $c['overdue'] ? ' ' . badge('overdue', (int) $c['overdue'] . ' overdue') : '') : '<span class="muted">—</span>' ?></td>
        <td><?= badge($c['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= view('components.pagination', ['p' => $p], null) ?>
  <?php endif; ?>
</div>
