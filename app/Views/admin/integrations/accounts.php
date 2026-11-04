<?php
$actions = '<form method="post" action="' . url('/integrations/providers/' . $provider['id'] . '/sync') . '" class="inline" data-confirm="Compare all HostFlow services with the accounts on this server?" data-confirm-style="primary">' . csrf_field() . '<button class="btn primary">Sync now</button></form>'
    . '<a class="btn ghost" href="' . url('/integrations') . '">All servers</a>';
?>
<?= view('components.heading', [
    'title' => $provider['name'],
    'subtitle' => 'Accounts on ' . $provider['hostname'] . ($provider['last_synced_at'] ? ' · last synced ' . datetime_fmt($provider['last_synced_at']) : ' · never synced'),
    'actions' => $actions,
], null) ?>

<?php if ($error): ?>
  <div class="alert error"><b>Could not list accounts:</b> <?= e($error) ?>
    <p>Check the API token, hostname and firewall (port <?= (int) $provider['port'] ?>), then <a href="<?= url('/integrations/providers/' . $provider['id'] . '/accounts') ?>">reload</a>.</p>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h3>Server accounts</h3><span class="muted small"><?= count($accounts) ?> account(s)</span></div>
  <?php if (!$accounts && !$error): ?><div class="empty">No accounts on this server.</div><?php endif; ?>
  <?php if ($accounts): ?>
  <div class="table-scroll"><table>
    <thead><tr><th>Username</th><th>Domain</th><th>Package</th><th class="text-right">Disk used</th><th>Server status</th><th>HostFlow</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a): $l = $linked[strtolower($a['username'])] ?? null; ?>
      <tr>
        <td><span class="mono"><?= e($a['username']) ?></span></td>
        <td><?= e($a['domain']) ?></td>
        <td><?= e($a['package']) ?></td>
        <td class="text-right"><?= isset($a['disk_used_mb']) ? e(number_format((float) $a['disk_used_mb'], 0)) . ' MB' : '—' ?></td>
        <td><?= !empty($a['suspended']) ? badge('suspended') : badge('active') ?></td>
        <td><?= $l ? '<a href="' . url('/hosting/view/' . $l['id']) . '">' . e($l['domain']) . '</a> ' . badge($l['status']) : badge('muted', 'Unmanaged') ?></td>
        <td class="text-right">
          <?php if (!$l && $plans): ?>
          <form method="post" action="<?= url('/integrations/providers/' . $provider['id'] . '/import') ?>" class="inline" style="display:flex;gap:6px;justify-content:flex-end">
            <?= csrf_field() ?>
            <input type="hidden" name="username" value="<?= e($a['username']) ?>">
            <select name="customer_id" required style="padding:6px 8px;border:1px solid var(--line);border-radius:6px;font-size:12px;max-width:170px">
              <option value="">Customer…</option>
              <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['business_name'] ?: $c['full_name']) ?></option><?php endforeach; ?>
            </select>
            <select name="plan_id" required style="padding:6px 8px;border:1px solid var(--line);border-radius:6px;font-size:12px;max-width:140px">
              <option value="">Plan…</option>
              <?php foreach ($plans as $pl): ?><option value="<?= (int) $pl['id'] ?>"><?= e($pl['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn small primary" type="submit">Import</button>
          </form>
          <?php elseif (!$l): ?><small class="muted">Map a plan to this provider first</small><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
