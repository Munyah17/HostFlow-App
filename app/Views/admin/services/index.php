<?php
$statuses = ['pending_payment', 'provisioning', 'active', 'suspended', 'provisioning_failed', 'cancellation_pending', 'terminated', 'cancelled'];
$base = $type === 'email' ? '/email-hosting' : '/hosting';
?>
<?= view('components.heading', [
    'title' => $title,
    'subtitle' => (int) $stats['total'] . ' services · ' . (int) $stats['active'] . ' active',
    'actions' => '<a class="btn primary" href="' . url('/hosting/create') . '">+ Add service</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><a href="<?= url($base, ['status' => 'active']) ?>"><span>Active</span><strong><?= (int) $stats['active'] ?></strong><small><?= $type === 'email' ? 'Email services' : 'Web hosting accounts' ?></small></a></div>
  <div class="stat"><a href="<?= url($base, ['status' => 'suspended']) ?>"><span>Suspended</span><strong><?= (int) $stats['suspended'] ?></strong><small>Needs attention</small></a></div>
  <div class="stat"><a href="<?= url($base, ['status' => 'provisioning_failed']) ?>"><span>Awaiting provisioning</span><strong><?= (int) $stats['pending'] ?></strong><small>Paid or failed</small></a></div>
</div>

<div class="card">
  <form class="filters" method="get" action="<?= url($base) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search domain, customer or username…">
    <select name="status" data-autosubmit>
      <option value="">All statuses</option>
      <?php foreach ($statuses as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option><?php endforeach; ?>
    </select>
    <button class="btn ghost" type="submit">Search</button>
  </form>

  <?php if (!$services): ?>
    <div class="empty">No services found.<?= $q || $status ? ' <a href="' . url($base) . '">Clear filters</a>.' : ' <a href="' . url('/hosting/create') . '">Add the first service</a>.' ?></div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Domain</th><th>Customer</th><th>Plan</th><th class="text-right">Price</th><th>Next due</th><th>Sync</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($services as $s): ?>
      <tr>
        <td><a href="<?= url('/hosting/view/' . $s['id']) ?>"><?= e($s['domain']) ?></a><?php if ($s['provider_username']): ?><br><small class="muted mono"><?= e($s['provider_username']) ?></small><?php endif; ?></td>
        <td><a href="<?= url('/customers/view/' . $s['customer_id']) ?>"><?= e($s['business_name'] ?: $s['full_name']) ?></a></td>
        <td><?= e($s['plan_name']) ?><?php if ($s['provider_name']): ?><br><small class="muted"><?= e($s['provider_name']) ?></small><?php endif; ?></td>
        <td class="text-right"><?= money($s['price']) ?> <small class="muted">/ <?= e(cycle_label($s['billing_cycle'])) ?></small></td>
        <td><?= date_fmt($s['next_due_date']) ?></td>
        <td><?= $s['sync_status'] !== 'unknown' ? badge($s['sync_status']) : '<span class="muted">—</span>' ?></td>
        <td><?= badge($s['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= view('components.pagination', ['p' => $p], null) ?>
  <?php endif; ?>
</div>
