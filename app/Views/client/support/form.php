<?= view('components.heading', [
    'title' => 'New support ticket',
    'subtitle' => 'Tell us what you need — we reply by email and here',
    'actions' => '<a class="btn ghost" href="' . url('/client/support') . '">Cancel</a>',
], null) ?>

<div class="card card-form">
  <form method="post" action="<?= url('/client/support') ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="field"><span>Related service</span>
        <select name="service_id">
          <option value="">None — general question</option>
          <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) old('service_id') === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['domain']) ?></option><?php endforeach; ?>
        </select>
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
          <?php foreach (['low' => 'Low — general question', 'normal' => 'Normal — something needs attention', 'high' => 'High — service problem', 'urgent' => 'Urgent — service is down'] as $key => $label): ?>
            <option value="<?= $key ?>" <?= old('priority', 'normal') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error('priority') ?>
      </label>
      <label class="field full"><span>Subject *</span><input type="text" name="subject" value="<?= e(old('subject')) ?>" required maxlength="190"><?= field_error('subject') ?></label>
      <label class="field full"><span>Describe the issue *</span><textarea name="message" required maxlength="20000" placeholder="What happened? Include any error messages."><?= e(old('message')) ?></textarea><?= field_error('message') ?></label>
    </div>
    <div class="form-actions"><button type="submit" class="btn primary">Send</button></div>
  </form>
</div>
