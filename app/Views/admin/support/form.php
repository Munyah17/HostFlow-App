<?= view('components.heading', [
    'title' => 'Open ticket',
    'subtitle' => 'Log a support request on a customer\'s behalf',
    'actions' => '<a class="btn ghost" href="' . url('/support') . '">Cancel</a>',
], null) ?>

<div class="card card-narrow">
  <form method="post" action="<?= url('/support') ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="field"><span>Customer *</span>
        <select name="customer_id" required onchange="if(this.value)location.href='<?= url('/support/create') ?>?customer_id='+this.value">
          <option value="">Select a customer…</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) old('customer_id', $customerId) === (int) $c['id'] ? 'selected' : '' ?>><?= e(($c['business_name'] ?: $c['full_name']) . ' · ' . $c['email']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error('customer_id') ?>
      </label>
      <label class="field"><span>Related service</span>
        <select name="service_id">
          <option value="">None</option>
          <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) old('service_id') === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['domain']) ?></option><?php endforeach; ?>
        </select>
        <?= $customerId && !$services ? '<span class="help">This customer has no active services.</span>' : '' ?>
        <?= field_error('service_id') ?>
      </label>
      <label class="field"><span>Category *</span>
        <select name="category">
          <?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>" <?= old('category') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?>
        </select>
        <?= field_error('category') ?>
      </label>
      <label class="field"><span>Priority *</span>
        <select name="priority">
          <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $key => $label): ?><option value="<?= $key ?>" <?= old('priority', 'normal') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <?= field_error('priority') ?>
      </label>
      <label class="field full"><span>Subject *</span><input type="text" name="subject" value="<?= e(old('subject')) ?>" required maxlength="190"><?= field_error('subject') ?></label>
      <label class="field full"><span>Details *</span><textarea name="message" required maxlength="20000"><?= e(old('message')) ?></textarea><?= field_error('message') ?></label>
    </div>
    <div class="form-actions"><button type="submit" class="btn primary">Open ticket</button></div>
  </form>
</div>
