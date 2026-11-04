<?= view('components.heading', [
    'title' => 'Settings',
    'subtitle' => $sections[$section],
    'actions' => '<a class="btn ghost" href="' . url('/settings/plugins') . '">Plugins</a><a class="btn ghost" href="' . url('/settings/system') . '">System</a>',
], null) ?>

<div class="tabs">
  <?php foreach ($sections as $key => $label): ?>
    <a href="<?= url('/settings/' . $key) ?>" class="<?= $section === $key ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <a href="<?= url('/settings/plugins') ?>" class="">Plugins</a>
  <a href="<?= url('/settings/system') ?>" class="">System</a>
</div>

<div class="card" style="max-width:760px">
  <form method="post" action="<?= url('/settings/' . $section) ?>">
    <?= csrf_field() ?>
    <?php foreach ($fields as [$key, $label, $type, $rule]): ?>
      <?php if ($type === 'checkbox'): ?>
        <label class="check" style="margin-bottom:12px"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= setting($key) === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php elseif (str_starts_with((string) $type, 'select:')): ?>
        <label class="field"><span><?= e($label) ?></span>
          <select name="<?= e($key) ?>">
            <?php foreach (explode(',', substr($type, 7)) as $opt): [$ov, $ol] = array_pad(explode('=>', $opt, 2), 2, $opt); ?>
              <option value="<?= e(trim($ov)) ?>" <?= (string) setting($key) === trim($ov) ? 'selected' : '' ?>><?= e(trim($ol)) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($key) ?>
        </label>
      <?php elseif ($type === 'textarea'): ?>
        <label class="field"><span><?= e($label) ?></span>
          <textarea name="<?= e($key) ?>" style="min-height:80px"><?= e(setting($key)) ?></textarea>
          <?= field_error($key) ?>
        </label>
      <?php elseif ($type === 'password'): ?>
        <label class="field"><span><?= e($label) ?></span>
          <input type="password" name="<?= e($key) ?>" value="" autocomplete="new-password" placeholder="<?= setting($key) !== '' && setting($key) !== null ? 'Saved — enter to replace' : '' ?>">
          <?= field_error($key) ?>
        </label>
      <?php else: ?>
        <label class="field"><span><?= e($label) ?></span>
          <input type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : ($type === 'url' ? 'url' : 'text')) ?>" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>">
          <?= field_error($key) ?>
        </label>
      <?php endif; ?>
    <?php endforeach; ?>
    <div class="form-actions"><button type="submit" class="btn primary">Save settings</button></div>
  </form>

  <?php if ($section === 'email'): ?>
    <h4 style="margin-top:22px">Send a test email</h4>
    <form method="post" action="<?= url('/settings/email/test') ?>" class="filters">
      <?= csrf_field() ?>
      <input type="email" name="email" placeholder="you@example.com" required>
      <button class="btn ghost" type="submit">Send test</button>
    </form>
  <?php endif; ?>
</div>
