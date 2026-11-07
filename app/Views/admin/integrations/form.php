<?php $editing = $provider !== null; ?>
<?= view('components.heading', [
    'title' => $editing ? 'Edit hosting server' : 'Add hosting server',
    'subtitle' => $editing ? $provider['name'] : 'Connect a WHM/cPanel server for automatic provisioning',
    'actions' => '<a class="btn ghost" href="' . url('/integrations') . '">Cancel</a>',
], null) ?>

<div class="card card-narrow">
  <form method="post" action="<?= url($editing ? '/integrations/providers/update/' . $provider['id'] : '/integrations/providers') ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="field"><span>Server name *</span><input type="text" name="name" value="<?= e(old('name', $provider['name'] ?? '')) ?>" required maxlength="120" placeholder="e.g. Main WHM server"><?= field_error('name') ?></label>
      <label class="field"><span>Type *</span>
        <select name="type">
          <?php foreach ($types as $key => $t): ?><option value="<?= e($key) ?>" <?= old('type', $provider['type'] ?? 'cpanel_whm') === $key ? 'selected' : '' ?>><?= e($t['label']) ?></option><?php endforeach; ?>
        </select>
        <?= field_error('type') ?>
      </label>
      <label class="field"><span>Hostname *</span><input type="text" name="hostname" value="<?= e(old('hostname', $provider['hostname'] ?? '')) ?>" required maxlength="190" placeholder="whm.example.com"><?= field_error('hostname') ?></label>
      <label class="field"><span>Port *</span><input type="number" name="port" value="<?= e(old('port', $provider['port'] ?? 2087)) ?>" min="1" max="65535" required><?= field_error('port') ?></label>
      <label class="field"><span>WHM username *</span><input type="text" name="username" value="<?= e(old('username', $provider['username'] ?? '')) ?>" required maxlength="100" autocomplete="off"><?= field_error('username') ?></label>
      <label class="field"><span>API token <?= $editing ? '(leave blank to keep current)' : '*' ?></span><input type="password" name="api_token" value="" maxlength="500" autocomplete="new-password" <?= $editing ? '' : 'required' ?>><?= field_error('api_token') ?><span class="help">WHM → Development → Manage API Tokens. Needs: listaccts, createacct, suspendacct, removesubacct…</span></label>
      <label class="field full"><span>Nameservers (comma-separated, shown to clients)</span><input type="text" name="nameservers" value="<?= e(old('nameservers', $provider['nameservers'] ?? '')) ?>" maxlength="255" placeholder="ns1.example.com, ns2.example.com"><?= field_error('nameservers') ?></label>
      <label class="field"><span>Status *</span>
        <select name="status">
          <option value="active" <?= old('status', $provider['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="disabled" <?= old('status', $provider['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <?= field_error('status') ?>
      </label>
      <label class="check end"><input type="checkbox" name="verify_ssl" value="1" <?= old('verify_ssl', $provider['verify_ssl'] ?? 1) ? 'checked' : '' ?>> Verify SSL certificate</label>
    </div>
    <div class="form-actions"><button type="submit" class="btn primary"><?= $editing ? 'Save changes' : 'Add server' ?></button></div>
  </form>
</div>
