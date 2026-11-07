<?php $editing = $customer !== null; ?>
<?= view('components.heading', [
    'title' => $editing ? 'Edit customer' : 'Add customer',
    'subtitle' => $editing ? ($customer['business_name'] ?: $customer['full_name']) : 'Create a customer record',
    'actions' => '<a class="btn ghost" href="' . url($editing ? '/customers/view/' . $customer['id'] : '/customers') . '">Cancel</a>',
], null) ?>

<div class="card card-narrow">
  <form method="post" action="<?= url($editing ? '/customers/update/' . $customer['id'] : '/customers') ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="field"><span>Full name *</span>
        <input type="text" name="full_name" value="<?= e(old('full_name', $customer['full_name'] ?? '')) ?>" required maxlength="150">
        <?= field_error('full_name') ?>
      </label>
      <label class="field"><span>Business name</span>
        <input type="text" name="business_name" value="<?= e(old('business_name', $customer['business_name'] ?? '')) ?>" maxlength="150">
        <?= field_error('business_name') ?>
      </label>
      <label class="field"><span>Email *</span>
        <input type="email" name="email" value="<?= e(old('email', $customer['email'] ?? '')) ?>" required maxlength="190">
        <?= field_error('email') ?>
      </label>
      <label class="field"><span>Phone</span>
        <input type="text" name="phone" value="<?= e(old('phone', $customer['phone'] ?? '')) ?>" maxlength="40">
        <?= field_error('phone') ?>
      </label>
      <label class="field full"><span>Address</span>
        <input type="text" name="address" value="<?= e(old('address', $customer['address'] ?? '')) ?>" maxlength="255">
        <?= field_error('address') ?>
      </label>
      <label class="field"><span>City</span>
        <input type="text" name="city" value="<?= e(old('city', $customer['city'] ?? '')) ?>" maxlength="100">
        <?= field_error('city') ?>
      </label>
      <label class="field"><span>Country *</span>
        <input type="text" name="country" value="<?= e(old('country', $customer['country'] ?? 'Zimbabwe')) ?>" required maxlength="100">
        <?= field_error('country') ?>
      </label>
      <label class="field full"><span>Notes (staff only)</span>
        <textarea name="notes" maxlength="5000"><?= e(old('notes', $customer['notes'] ?? '')) ?></textarea>
        <?= field_error('notes') ?>
      </label>
    </div>
    <?php if (!$editing): ?>
      <label class="check"><input type="checkbox" name="create_login" value="1" <?= old('create_login') ? 'checked' : '' ?>> Create a client portal login and email a password set-up link</label>
    <?php endif; ?>
    <div class="form-actions">
      <button type="submit" class="btn primary"><?= $editing ? 'Save changes' : 'Create customer' ?></button>
    </div>
  </form>
</div>
