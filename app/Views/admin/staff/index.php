<?= view('components.heading', [
    'title' => 'Administrators',
    'subtitle' => 'Staff who can access this admin area',
], null) ?>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Staff accounts</h3></div>
    <div class="table-scroll"><table>
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th></tr></thead>
      <tbody>
      <?php foreach ($staff as $u): ?>
        <tr>
          <td><b><?= e($u['name']) ?></b></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e(\App\Core\Auth::ROLE_LABELS[$u['role']] ?? $u['role']) ?></td>
          <td><?= badge($u['status']) ?></td>
          <td><small><?= datetime_fmt($u['last_login_at']) ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>

    <?php foreach ($staff as $u): ?>
    <details style="border-top:1px solid var(--line);padding:10px 0">
      <summary class="small" style="cursor:pointer">Manage <b><?= e($u['name']) ?></b></summary>
      <form method="post" action="<?= url('/staff/' . $u['id'] . '/update') ?>" class="filters" style="margin-top:10px">
        <?= csrf_field() ?>
        <select name="role">
          <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
          <option value="super_admin" <?= $u['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
        </select>
        <select name="status">
          <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="disabled" <?= $u['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <input type="password" name="password" placeholder="New password (optional)" autocomplete="new-password" style="min-width:170px">
        <input type="password" name="password_confirmation" placeholder="Repeat password" autocomplete="new-password" style="min-width:170px">
        <button class="btn ghost small" type="submit">Save</button>
      </form>
    </details>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Add administrator</h3></div>
    <form method="post" action="<?= url('/staff') ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Name *</span><input type="text" name="name" value="<?= e(old('name')) ?>" required maxlength="150"><?= field_error('name') ?></label>
      <label class="field"><span>Email *</span><input type="email" name="email" value="<?= e(old('email')) ?>" required maxlength="190"><?= field_error('email') ?></label>
      <label class="field"><span>Role *</span>
        <select name="role">
          <option value="admin" <?= old('role') === 'admin' ? 'selected' : '' ?>>Admin — manage customers, billing, support</option>
          <option value="super_admin" <?= old('role') === 'super_admin' ? 'selected' : '' ?>>Super Admin — full access incl. settings</option>
        </select>
        <?= field_error('role') ?>
      </label>
      <label class="field"><span>Password *</span><input type="password" name="password" required autocomplete="new-password"><?= field_error('password') ?><span class="help">Minimum <?= (int) setting('min_password_length', 10) ?> characters.</span></label>
      <label class="field"><span>Repeat password *</span><input type="password" name="password_confirmation" required autocomplete="new-password"></label>
      <div class="form-actions"><button type="submit" class="btn primary">Create account</button></div>
    </form>
  </div>
</div>
