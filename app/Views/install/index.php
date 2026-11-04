<?php $allOk = !array_filter($requirements, fn ($r) => $r[3] && !$r[1]); ?>
<h1>Install HostFlow</h1>
<p class="sub">Version <?= e(app_version()) ?> · This wizard creates the database tables, your configuration file and the Super Admin account.</p>

<h4 style="font-size:13px;margin:0 0 8px">1. Server requirements</h4>
<div class="table-scroll" style="margin-bottom:18px">
  <table class="compact" style="min-width:0">
    <tbody>
    <?php foreach ($requirements as [$label, $ok, $detail, $required]): ?>
      <tr><td><?= e($label) ?><?= $required ? '' : ' <span class="muted">(recommended)</span>' ?></td><td class="text-right"><?= $ok ? badge('active', 'OK' . ($detail ? " · {$detail}" : '')) : badge($required ? 'failed' : 'pending', $required ? 'Missing' : 'Unavailable') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if (!$allOk): ?><div class="alert error">Resolve the missing requirements above, then refresh this page.</div><?php endif; ?>

<form method="post" action="<?= url('/install') ?>" autocomplete="off">
  <?= csrf_field() ?>
  <fieldset>
    <legend>2. Database (MySQL / MariaDB)</legend>
    <p class="help" style="margin-top:0">Create an empty database and user in cPanel → MySQL Databases, grant the user ALL PRIVILEGES on it, then enter the details here.</p>
    <div class="form-grid">
      <label>Database host<input name="db_host" value="<?= e(old('db_host', 'localhost')) ?>" required><?= field_error('db_host') ?></label>
      <label>Port<input name="db_port" value="<?= e(old('db_port', '3306')) ?>" required inputmode="numeric"><?= field_error('db_port') ?></label>
      <label>Database name<input name="db_name" value="<?= e(old('db_name')) ?>" required><?= field_error('db_name') ?></label>
      <label>Database username<input name="db_user" value="<?= e(old('db_user')) ?>" required><?= field_error('db_user') ?></label>
      <label class="full">Database password<input type="password" name="db_pass" autocomplete="new-password"></label>
    </div>
  </fieldset>
  <fieldset>
    <legend>3. Application</legend>
    <div class="form-grid">
      <label class="full">Application URL<input type="url" name="app_url" value="<?= e(old('app_url', $suggestedUrl)) ?>" required><?= field_error('app_url') ?><span class="help">The public HTTPS address of HostFlow, e.g. https://billing.globalspaceweb.co.zw</span></label>
      <label>Business name<input name="business_name" value="<?= e(old('business_name', 'Global Space Web Hosting')) ?>" required><?= field_error('business_name') ?></label>
      <label>Business email<input type="email" name="business_email" value="<?= e(old('business_email', 'support@globalspaceweb.co.zw')) ?>" required><?= field_error('business_email') ?></label>
    </div>
  </fieldset>
  <fieldset>
    <legend>4. Super Admin account</legend>
    <div class="form-grid">
      <label>Full name<input name="admin_name" value="<?= e(old('admin_name')) ?>" required><?= field_error('admin_name') ?></label>
      <label>Email address<input type="email" name="admin_email" value="<?= e(old('admin_email')) ?>" required><?= field_error('admin_email') ?></label>
      <label>Password<input type="password" name="admin_password" required minlength="10" autocomplete="new-password"><?= field_error('admin_password') ?><span class="help">At least 10 characters.</span></label>
      <label>Confirm password<input type="password" name="admin_password_confirmation" required autocomplete="new-password"></label>
    </div>
  </fieldset>
  <button type="submit" class="btn primary" <?= $allOk ? '' : 'disabled' ?>>Install HostFlow</button>
</form>
