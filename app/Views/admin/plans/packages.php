<?php
$fmtMb = function ($mb) {
    if ($mb === null) {
        return 'Unlimited';
    }
    $gb = $mb / 1024;
    return ($gb >= 1 ? rtrim(rtrim(number_format($gb, 2), '0'), '.') . ' GB' : rtrim(rtrim(number_format($mb, 0), '0'), '.') . ' MB');
};
$fmtNum = fn ($v) => $v === null ? '∞' : (string) (int) $v;
$specFields = [
    'email_accounts' => 'Email accounts',
    'databases' => 'Databases',
    'domains' => 'Addon domains',
    'subdomains' => 'Subdomains',
    'parked_domains' => 'Parked domains',
    'ftp_accounts' => 'FTP accounts',
];
$pkgForm = function (array $pv, array $pkg = []) use ($specFields) {
    $v = fn (string $k, string $unit = '') => e($pkg[$k] ?? '');
    $gb = fn (string $k) => isset($pkg[$k]) && $pkg[$k] !== null ? e(rtrim(rtrim(number_format($pkg[$k] / 1024, 2), '0'), '.')) : '';
    ?>
    <div class="form-grid three">
      <label class="field"><span>Disk (GB)</span><input type="number" name="disk_gb" min="0" step="0.01" value="<?= $gb('disk_mb') ?>" placeholder="Unlimited"></label>
      <label class="field"><span>Bandwidth (GB)</span><input type="number" name="bandwidth_gb" min="0" step="0.01" value="<?= $gb('bandwidth_mb') ?>" placeholder="Unlimited"></label>
      <?php foreach ($specFields as $k => $label): ?>
        <label class="field"><span><?= e($label) ?></span><input type="number" name="<?= $k ?>" min="0" step="1" value="<?= $v($k) ?>" placeholder="∞"></label>
      <?php endforeach; ?>
    </div>
    <p class="help">Leave a field empty for unlimited.<?= empty($pkg) ? '' : ' The package name cannot be changed.' ?></p>
    <?php
};
?>
<?= view('components.heading', [
    'title' => 'Server packages',
    'subtitle' => 'WHM/cPanel packages on your hosting servers — create, edit and delete them here',
    'actions' => '<a class="btn ghost" href="' . url('/hosting/plans') . '">Plans &amp; pricing</a>',
], null) ?>

<?php if (!$providers): ?>
  <div class="card"><div class="empty">No active cPanel/WHM providers. <a href="<?= url('/integrations/providers/create') ?>">Add a provider</a> first.</div></div>
<?php endif; ?>

<?php foreach ($providers as $pv): ?>
<div class="card">
  <div class="card-head">
    <h3><?= e($pv['row']['name']) ?></h3>
    <span class="muted small"><?= e($pv['row']['hostname']) ?>:<?= (int) $pv['row']['port'] ?> · <?= count($pv['packages']) ?> package(s)</span>
  </div>

  <?php if ($pv['error']): ?>
    <div class="alert error"><b>Could not list packages:</b> <?= e($pv['error']) ?></div>
  <?php endif; ?>

  <?php if ($pv['packages']): ?>
  <div class="plan-grid three">
    <?php foreach ($pv['packages'] as $pkg):
      $name = (string) $pkg['name'];
      $acctN = $pv['account_usage'][$name] ?? 0;
      $svcN = $pv['service_usage'][$name] ?? 0;
      $planN = $pv['plan_usage'][$name] ?? 0;
      $usedBy = trim(implode(', ', array_filter([
          $acctN ? $acctN . ' account(s)' : '',
          $svcN ? $svcN . ' service(s)' : '',
          $planN ? $planN . ' plan(s)' : '',
      ])));
    ?>
    <div class="plan">
      <div class="plan-top">
        <div><h4 class="mono"><?= e($name) ?></h4><small class="muted"><?= e($fmtMb($pkg['disk_mb'])) ?> disk · <?= e($fmtMb($pkg['bandwidth_mb'])) ?> bw</small></div>
        <small class="muted"><?= $usedBy !== '' ? e($usedBy) : 'unused' ?></small>
      </div>
      <ul class="small">
        <li><?= e($fmtNum($pkg['email_accounts'])) ?> email · <?= e($fmtNum($pkg['databases'])) ?> databases · <?= e($fmtNum($pkg['domains'])) ?> addon domains</li>
        <?php if ($pkg['subdomains'] !== null): ?><li><?= e($fmtNum($pkg['subdomains'])) ?> subdomains</li><?php endif; ?>
        <?php if ($pkg['parked_domains'] !== null): ?><li><?= e($fmtNum($pkg['parked_domains'])) ?> parked</li><?php endif; ?>
        <?php if ($pkg['ftp_accounts'] !== null): ?><li><?= e($fmtNum($pkg['ftp_accounts'])) ?> FTP</li><?php endif; ?>
      </ul>
      <details class="fold">
        <summary>Edit limits</summary>
        <form method="post" action="<?= url('/hosting/packages/save') ?>" class="mt-10">
          <?= csrf_field() ?>
          <input type="hidden" name="provider_id" value="<?= (int) $pv['row']['id'] ?>">
          <input type="hidden" name="pkg" value="<?= e($name) ?>">
          <?php $pkgForm($pv, $pkg); ?>
          <div class="form-actions"><button class="btn primary" type="submit">Save changes</button></div>
        </form>
      </details>
      <form method="post" action="<?= url('/hosting/packages/delete') ?>" class="mt-10"
        data-confirm="Delete the '<?= e($name) ?>' package on <?= e($pv['row']['name']) ?>?<?= $usedBy !== '' ? ' It is still used by ' . e($usedBy) . ' — deletion will be refused.' : '' ?>"
        data-confirm-button="Delete package">
        <?= csrf_field() ?>
        <input type="hidden" name="provider_id" value="<?= (int) $pv['row']['id'] ?>">
        <input type="hidden" name="pkg" value="<?= e($name) ?>">
        <button type="submit" class="btn small danger">Delete package…</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <details class="fold card-section">
    <summary><b>New package on <?= e($pv['row']['name']) ?></b></summary>
    <form method="post" action="<?= url('/hosting/packages/save') ?>" class="mt-10">
      <?= csrf_field() ?>
      <input type="hidden" name="provider_id" value="<?= (int) $pv['row']['id'] ?>">
      <label class="field"><span>Package name *</span><input type="text" name="name" required maxlength="64" placeholder="e.g. globalsp_Professional"><span class="help">Shown in WHM; set this as the provider package on a plan to sell it.</span><?= field_error('name') ?></label>
      <?php $pkgForm($pv); ?>
      <div class="form-actions"><button class="btn primary" type="submit">Create package</button></div>
    </form>
  </details>
</div>
<?php endforeach; ?>
