<?= view('components.heading', [
    'title' => $plugin['manifest']['name'] ?? $plugin['plugin_id'],
    'subtitle' => 'Plugin configuration',
    'actions' => '<a class="btn ghost" href="' . url('/settings/plugins') . '">All plugins</a>',
], null) ?>

<div class="card" style="max-width:760px">
  <?php if (!$fields): ?>
    <div class="empty">This plugin declares no settings.</div>
  <?php else: ?>
  <form method="post" action="<?= url('/settings/plugins/' . $plugin['id'] . '/configure') ?>">
    <?= csrf_field() ?>
    <?php foreach ($fields as $f):
      $key = $f['key'];
      $type = $f['type'] ?? 'text';
      $label = $f['label'] ?? ucfirst(str_replace('_', ' ', $key));
      $value = $values[$key] ?? ($f['default'] ?? '');
    ?>
      <?php if ($type === 'checkbox'): ?>
        <label class="check" style="margin-bottom:12px"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= (string) $value === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php elseif ($type === 'select'): ?>
        <label class="field"><span><?= e($label) ?></span>
          <select name="<?= e($key) ?>">
            <?php foreach (($f['options'] ?? []) as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= (string) $value === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?>
          </select>
        </label>
      <?php elseif ($type === 'textarea'): ?>
        <label class="field"><span><?= e($label) ?></span><textarea name="<?= e($key) ?>"><?= e($value) ?></textarea></label>
      <?php elseif ($type === 'password'): ?>
        <label class="field"><span><?= e($label) ?></span><input type="password" name="<?= e($key) ?>" value="" autocomplete="new-password" placeholder="<?= (string) $value !== '' ? 'Saved — enter to replace' : '' ?>"></label>
      <?php else: ?>
        <label class="field"><span><?= e($label) ?></span><input type="text" name="<?= e($key) ?>" value="<?= e($value) ?>"></label>
      <?php endif; ?>
      <?php if (!empty($f['help'])): ?><span class="help" style="margin-top:-8px;display:block;margin-bottom:12px"><?= e($f['help']) ?></span><?php endif; ?>
    <?php endforeach; ?>
    <div class="form-actions"><button type="submit" class="btn primary">Save</button></div>
  </form>
  <?php endif; ?>
</div>
