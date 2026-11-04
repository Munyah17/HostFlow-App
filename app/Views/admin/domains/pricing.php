<?= view('components.heading', [
    'title' => 'Domain pricing',
    'subtitle' => count($pricing) . ' extension(s) on sale',
    'actions' => '<a class="btn ghost" href="' . url('/domains') . '">All domains</a>',
], null) ?>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Extensions</h3></div>
    <?php if (!$pricing): ?>
      <div class="empty">No extensions priced yet.<?= $canEdit ? ' Add the first below.' : '' ?></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Extension</th><th class="text-right">Registration /yr</th><th class="text-right">Renewal /yr</th><th class="text-right">Transfer /yr</th><th>Status</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($pricing as $t): ?>
        <tr>
          <td><span class="mono"><?= e($t['tld']) ?></span></td>
          <td class="text-right"><?= money($t['register_price']) ?></td>
          <td class="text-right"><?= money($t['renew_price']) ?></td>
          <td class="text-right"><?= $t['transfer_price'] !== null ? money($t['transfer_price']) : '—' ?></td>
          <td><?= badge($t['status']) ?></td>
          <?php if ($canEdit): ?>
          <td class="text-right">
            <details class="inline-edit">
              <summary class="btn small ghost">Edit</summary>
              <form method="post" action="<?= url('/domains/pricing/' . $t['id'] . '/update') ?>" class="inline-edit-form">
                <?= csrf_field() ?>
                <label>Register <input type="number" name="register_price" value="<?= e($t['register_price']) ?>" min="0" step="0.01" required></label>
                <label>Renew <input type="number" name="renew_price" value="<?= e($t['renew_price']) ?>" min="0" step="0.01" required></label>
                <label>Transfer <input type="number" name="transfer_price" value="<?= e($t['transfer_price']) ?>" min="0" step="0.01"></label>
                <label>Status <select name="status"><option value="active" <?= $t['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="hidden" <?= $t['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option></select></label>
                <button class="btn primary" type="submit">Save</button>
              </form>
            </details>
            <form method="post" action="<?= url('/domains/pricing/' . $t['id'] . '/delete') ?>" class="inline" data-confirm="Remove <?= e($t['tld']) ?> pricing? Clients will no longer be able to order it."><?= csrf_field() ?><button class="btn small danger">Delete</button></form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($canEdit): ?>
  <div class="card">
    <div class="card-head"><h3>Add an extension</h3></div>
    <form method="post" action="<?= url('/domains/pricing') ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Extension *</span><input type="text" name="tld" value="<?= e(old('tld')) ?>" required placeholder=".co.zw" maxlength="30"><?= field_error('tld') ?></label>
      <label class="field"><span>Registration price / year *</span><input type="number" name="register_price" value="<?= e(old('register_price')) ?>" min="0" step="0.01" required><?= field_error('register_price') ?></label>
      <label class="field"><span>Renewal price / year *</span><input type="number" name="renew_price" value="<?= e(old('renew_price')) ?>" min="0" step="0.01" required><?= field_error('renew_price') ?></label>
      <label class="field"><span>Transfer price / year</span><input type="number" name="transfer_price" value="<?= e(old('transfer_price')) ?>" min="0" step="0.01"><?= field_error('transfer_price') ?></label>
      <div class="form-actions"><button type="submit" class="btn primary">Add extension</button></div>
    </form>
  </div>
  <?php endif; ?>
</div>
