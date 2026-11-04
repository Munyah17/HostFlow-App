<?= view('components.heading', [
    'title' => 'System & updates',
    'subtitle' => 'HostFlow v' . $version . ' · PHP ' . $php,
    'actions' => '<a class="btn ghost" href="' . url('/settings') . '">Settings</a>',
], null) ?>

<div class="stats three">
  <div class="stat"><span>Current version</span><strong><?= e($version) ?></strong><small><?= $lastVersion ? 'Upgraded from ' . e($lastVersion) : 'Fresh install' ?></small></div>
  <div class="stat"><span>Pending migrations</span><strong><?= count($pending) ?></strong><small><?= $pending ? 'Update needed' : 'Database up to date' ?></small></div>
  <div class="stat"><span>Backups</span><strong><?= count($backups) ?></strong><small>Kept in storage/backups</small></div>
</div>

<?php foreach ($incompatible as $pid => $min): ?>
  <div class="alert warning"><b>Plugin '<?= e($pid) ?>'</b> requires HostFlow <?= e($min) ?> or newer and is not running.</div>
<?php endforeach; ?>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Database migrations</h3></div>
    <?php if (!$pending): ?>
      <div class="empty">The database is up to date.</div>
    <?php else: ?>
      <div class="events">
        <?php foreach ($pending as $m): ?><div><b class="mono"><?= e($m) ?></b><small>Pending</small></div><?php endforeach; ?>
      </div>
      <div class="form-actions">
        <form method="post" action="<?= url('/settings/system/migrate') ?>" data-confirm="Apply <?= count($pending) ?> pending migration(s)? A backup is created first." data-confirm-style="primary"><?= csrf_field() ?><button class="btn primary">Run migrations</button></form>
      </div>
    <?php endif; ?>

    <h4>Update history</h4>
    <?php if (!$history): ?><div class="empty">No updates recorded.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>When</th><th>Version</th><th>Result</th><th>Message</th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr><td><small><?= datetime_fmt($h['created_at']) ?></small></td><td><?= e(($h['from_version'] ?: 'install') . ' → ' . $h['to_version']) ?></td><td><?= badge($h['status']) ?></td><td class="wrap"><small><?= e(str_limit($h['message'], 100)) ?></small></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Backups</h3>
      <form method="post" action="<?= url('/settings/system/backup') ?>" class="inline" data-confirm="Create a database backup now?" data-confirm-style="primary"><?= csrf_field() ?><button class="btn small ghost">New backup</button></form>
    </div>
    <?php if (!$backups): ?><div class="empty">No backups yet.</div><?php else: ?>
    <div class="events">
      <?php foreach ($backups as $b): ?><div><b class="mono"><?= e($b['name']) ?></b><small><?= e(date('d M Y, H:i', (int) $b['time'])) ?> · <?= e(number_format($b['size'] / 1024, 0)) ?> KB</small></div><?php endforeach; ?>
    </div>
    <p class="help">Backups stay on this server. Download copies off-server regularly via FTP or your hosting file manager.</p>
    <?php endif; ?>

    <h4>Cron job</h4>
    <p class="small">Schedule HostFlow automation in cPanel → Cron Jobs, every 5 minutes:</p>
    <p class="mono small" style="background:#f6f9ff;padding:10px;border-radius:8px;word-break:break-all">*/5 * * * * php -q <?= e(BASE_PATH . '/cron/run.php') ?></p>
    <p class="small muted">Or via URL (only when CLI cron is unavailable): <span class="mono"><?= e($cronUrl) ?><?= $cronToken !== '' ? '?token=…' : '' ?></span></p>
  </div>
</div>
