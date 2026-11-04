<?= view('components.heading', [
    'title' => 'Integrations',
    'subtitle' => 'Hosting servers HostFlow provisions accounts on',
    'actions' => '<a class="btn primary" href="' . url('/integrations/providers/create') . '">+ Add server</a>',
], null) ?>

<div class="card">
  <div class="card-head"><h3>Hosting providers</h3></div>
  <?php if (!$providers): ?>
    <div class="empty">No servers configured. <a href="<?= url('/integrations/providers/create') ?>">Add your WHM server</a> to start provisioning accounts.</div>
  <?php else: ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Server</th><th>Type</th><th>Host</th><th class="text-right">Services</th><th>Connection</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($providers as $p): ?>
      <tr>
        <td><b><?= e($p['name']) ?></b></td>
        <td><?= e($types[$p['type']]['label'] ?? $p['type']) ?></td>
        <td><span class="mono"><?= e($p['hostname']) ?>:<?= (int) $p['port'] ?></span><br><small class="muted"><?= e($p['nameservers'] ?: '') ?></small></td>
        <td class="text-right"><?= (int) $p['services'] ?></td>
        <td><?= $p['last_check_ok'] === null ? '<span class="muted">Not tested</span>' : ($p['last_check_ok'] ? badge('active', 'Connected') : badge('failed', 'Error')) ?><?php if ($p['last_check_message']): ?><br><small class="muted"><?= e(str_limit($p['last_check_message'], 60)) ?></small><?php endif; ?></td>
        <td><?= badge($p['status']) ?></td>
        <td class="text-right">
          <div class="actions" style="justify-content:flex-end">
            <a class="btn small ghost" href="<?= url('/integrations/providers/' . $p['id'] . '/accounts') ?>">Accounts</a>
            <a class="btn small ghost" href="<?= url('/integrations/providers/edit/' . $p['id']) ?>">Edit</a>
            <form method="post" action="<?= url('/integrations/providers/' . $p['id'] . '/test') ?>" class="inline"><?= csrf_field() ?><button class="btn small ghost" type="submit">Test</button></form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
