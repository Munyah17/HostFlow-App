<?= view('components.heading', [
    'title' => 'Plugins',
    'subtitle' => 'Extend HostFlow with optional add-ons',
    'actions' => '<a class="btn ghost" href="' . url('/settings') . '">Settings</a>',
], null) ?>

<?php foreach ($loadErrors as $pid => $err): ?>
  <div class="alert warning"><b><?= e($pid) ?>:</b> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Installed &amp; discovered</h3></div>
    <?php if (!$plugins): ?><div class="empty">No plugins found. Upload a plugin zip to get started.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Plugin</th><th>Version</th><th>Source</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($plugins as $pid => $p):
        $record = $p['record'] ?? null;
        $manifest = $p['manifest'] ?? null;
        $status = $record['status'] ?? 'installed';
        $name = $manifest['name'] ?? $record['name'] ?? $pid;
      ?>
        <tr>
          <td><b><?= e($name) ?></b> <small class="muted mono"><?= e($pid) ?></small><br>
            <small class="muted"><?= e($manifest['description'] ?? '') ?><?= $p['manifest_error'] ?? null ? ' <span style="color:#c54455">' . e($p['manifest_error']) . '</span>' : '' ?><?= !($p['compatible'] ?? true) ? ' <span style="color:#c54455">Requires newer HostFlow</span>' : '' ?></small>
          </td>
          <td><?= e($manifest['version'] ?? ($record['version'] ?? '—')) ?></td>
          <td><?= e(ucfirst($p['source'] ?? $record['source'] ?? 'uploaded')) ?></td>
          <td><?= badge($status) ?><?= $record['last_error'] ?? null ? '<br><small class="muted">' . e(str_limit($record['last_error'], 60)) . '</small>' : '' ?></td>
          <td class="text-right">
            <div class="actions" style="justify-content:flex-end">
              <?php if ($record): ?>
                <a class="btn small ghost" href="<?= url('/settings/plugins/' . $record['id'] . '/configure') ?>">Configure</a>
              <?php endif; ?>
              <?php if ($status === 'installed' || $status === 'disabled'): ?>
                <form method="post" action="<?= url('/settings/plugins/' . ($record['id'] ?? 0) . '/enable') ?>" class="inline"><?= csrf_field() ?><button class="btn small primary" <?= $record ? '' : 'disabled' ?>>Enable</button></form>
              <?php elseif ($status === 'enabled'): ?>
                <form method="post" action="<?= url('/settings/plugins/' . $record['id'] . '/disable') ?>" class="inline" data-confirm="Disable this plugin?"><?= csrf_field() ?><button class="btn small ghost">Disable</button></form>
              <?php endif; ?>
              <?php if ($record && in_array($status, ['installed', 'disabled', 'error'], true)): ?>
                <form method="post" action="<?= url('/settings/plugins/' . $record['id'] . '/uninstall') ?>" class="inline" data-confirm="Uninstall this plugin? Check the box below to also delete its data." data-confirm-button="Uninstall">
                  <?= csrf_field() ?>
                  <label class="check small" style="display:inline-flex"><input type="checkbox" name="delete_data" value="1"> data</label>
                  <button class="btn small danger">Uninstall</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Upload a plugin</h3><p>ZIP containing a plugin.json manifest.</p></div>
    <form method="post" action="<?= url('/settings/plugins/upload') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label class="field"><span>Plugin archive (.zip)</span><input type="file" name="plugin" accept=".zip" required><?= field_error('plugin') ?></label>
      <div class="form-actions"><button type="submit" class="btn primary">Install</button></div>
    </form>
    <p class="help" style="margin-top:14px">Plugins run with full access to your data. Only install plugins from sources you trust.</p>
  </div>
</div>
