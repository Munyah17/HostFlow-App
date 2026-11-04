<?= view('components.heading', [
    'title' => 'My domains',
    'subtitle' => count($domains) . ' domain(s)',
    'actions' => $ordering ? '<a class="btn primary" href="' . url('/client/domains/order') . '">+ Register a domain</a>' : '',
], null) ?>

<div class="card">
  <?php if (!$domains): ?>
    <div class="empty">No domains yet.<?= $ordering ? ' <a href="' . url('/client/domains/order') . '">Register your first domain</a>.' : '' ?></div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Domain</th><th>Registered</th><th>Expires</th><th class="text-right">Renewal</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($domains as $d): ?>
      <tr>
        <td><a href="<?= url('/client/domains/' . $d['id']) ?>"><?= e($d['domain']) ?></a></td>
        <td><?= date_fmt($d['registered_at']) ?></td>
        <td><?= date_fmt($d['expiry_date']) ?><?php if ($d['status'] === 'active' && $d['expiry_date'] && strtotime($d['expiry_date']) - time() < 30 * 86400): ?> <span class="badge pending">soon</span><?php endif; ?></td>
        <td class="text-right"><?= $d['renewal_price'] > 0 ? money($d['renewal_price']) . '<small class="muted">/yr</small>' : '—' ?></td>
        <td><?= badge($d['status']) ?></td>
        <td class="text-right"><a class="btn small ghost" href="<?= url('/client/domains/' . $d['id']) ?>">Manage</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
