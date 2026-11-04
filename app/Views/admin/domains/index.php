<?php $statuses = \App\Services\DomainService::STATUSES; ?>
<?= view('components.heading', [
    'title' => 'Domains',
    'subtitle' => (int) $stats['total'] . ' domains · ' . (int) $stats['active'] . ' active',
    'actions' => '<a class="btn ghost" href="' . url('/domains/pricing') . '">Pricing</a><a class="btn primary" href="' . url('/domains/create') . '">+ Add domain</a>',
], null) ?>

<div class="stats four">
  <div class="stat"><a href="<?= url('/domains', ['status' => 'active']) ?>"><span>Active</span><strong><?= (int) $stats['active'] ?></strong><small>Registered domains</small></a></div>
  <div class="stat"><a href="<?= url('/domains', ['status' => 'pending_registration']) ?>"><span>Awaiting registration</span><strong><?= (int) $stats['pending'] ?></strong><small>Paid — register at registrar</small></a></div>
  <div class="stat"><a href="<?= url('/domains', ['status' => 'expired']) ?>"><span>Expired</span><strong><?= (int) $stats['expired'] ?></strong><small>Renewable</small></a></div>
  <div class="stat"><span>Expiring soon</span><strong><?= (int) $stats['expiring'] ?></strong><small>Next 30 days</small></div>
</div>

<div class="card">
  <form class="filters" method="get" action="<?= url('/domains') ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search domain, customer or registrar…">
    <select name="status" data-autosubmit>
      <option value="">All statuses</option>
      <?php foreach ($statuses as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option><?php endforeach; ?>
    </select>
    <button class="btn ghost" type="submit">Search</button>
  </form>

  <?php if (!$domains): ?>
    <div class="empty">No domains found.<?= $q || $status ? ' <a href="' . url('/domains') . '">Clear filters</a>.' : ' <a href="' . url('/domains/create') . '">Add the first domain</a>.' ?></div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Domain</th><th>Customer</th><th>Registrar</th><th>Registered</th><th>Expires</th><th class="text-right">Renewal</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($domains as $d): ?>
      <tr>
        <td><a href="<?= url('/domains/view/' . $d['id']) ?>"><?= e($d['domain']) ?></a><?php if ($d['auto_renew']): ?><br><small class="muted">auto-renew</small><?php endif; ?></td>
        <td><a href="<?= url('/customers/view/' . $d['customer_id']) ?>"><?= e($d['business_name'] ?: $d['full_name']) ?></a></td>
        <td><?= e($d['registrar'] ?: '—') ?></td>
        <td><?= date_fmt($d['registered_at']) ?></td>
        <td><?= date_fmt($d['expiry_date']) ?><?php if ($d['status'] === 'active' && $d['expiry_date'] && strtotime($d['expiry_date']) - time() < 30 * 86400): ?> <span class="badge pending">soon</span><?php endif; ?></td>
        <td class="text-right"><?= $d['renewal_price'] > 0 ? money($d['renewal_price']) : '—' ?></td>
        <td><?= badge($d['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= view('components.pagination', ['p' => $p], null) ?>
  <?php endif; ?>
</div>
