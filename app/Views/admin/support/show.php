<?= view('components.heading', [
    'title' => $t['number'] . ' · ' . $t['subject'],
    'subtitle' => ($t['business_name'] ?: $t['full_name']) . ' · ' . e($t['category']) . ' · opened ' . datetime_fmt($t['created_at']),
    'actions' => '<a class="btn ghost" href="' . url('/customers/view/' . $t['customer_id']) . '">Customer</a><a class="btn ghost" href="' . url('/support') . '">All tickets</a>',
], null) ?>

<div class="columns">
  <div class="card">
    <div class="card-head"><h3>Conversation</h3><span><?= badge($t['status']) ?> <?= badge($t['priority']) ?></span></div>
    <div class="thread">
      <?php foreach ($messages as $m): ?>
        <div class="msg <?= $m['is_internal'] ? 'internal' : ($m['role'] === 'client' ? '' : 'staff') ?>">
          <header>
            <b><?= e($m['name'] ?: $t['full_name']) ?><?= $m['is_internal'] ? ' <span class="badge pending">internal note</span>' : '' ?></b>
            <small><?= datetime_fmt($m['created_at']) ?></small>
          </header>
          <div class="body"><?= e($m['message']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <h4>Reply</h4>
    <form method="post" action="<?= url('/support/tickets/' . $t['id'] . '/reply') ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Message</span><textarea name="message" required maxlength="20000"></textarea><?= field_error('message') ?></label>
      <label class="check"><input type="checkbox" name="internal" value="1"> Internal note — not emailed to the customer</label>
      <div class="form-actions"><button type="submit" class="btn primary">Send reply</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Ticket details</h3></div>
    <dl class="details single">
      <div><dt>Customer</dt><dd><a href="<?= url('/customers/view/' . $t['customer_id']) ?>"><?= e($t['business_name'] ?: $t['full_name']) ?></a> · <?= e($t['email']) ?></dd></div>
      <div><dt>Related service</dt><dd><?= $t['domain'] ? '<a href="' . url('/hosting/view/' . $t['service_id']) . '">' . e($t['domain']) . '</a>' : '—' ?></dd></div>
      <div><dt>Last reply</dt><dd><?= datetime_fmt($t['last_reply_at']) ?> by <?= $t['last_reply_by'] === 'client' ? 'customer' : 'staff' ?></dd></div>
      <div><dt>Assigned to</dt><dd><?= e($t['assignee'] ?: 'Unassigned') ?></dd></div>
    </dl>

    <h4>Update</h4>
    <form method="post" action="<?= url('/support/tickets/' . $t['id'] . '/update') ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <label class="field"><span>Status</span>
          <select name="status">
            <?php foreach (['open', 'pending', 'resolved', 'closed'] as $st): ?><option value="<?= $st ?>" <?= $t['status'] === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="field"><span>Priority</span>
          <select name="priority">
            <?php foreach (['low', 'normal', 'high', 'urgent'] as $pr): ?><option value="<?= $pr ?>" <?= $t['priority'] === $pr ? 'selected' : '' ?>><?= e(ucfirst($pr)) ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <label class="field"><span>Assigned to</span>
        <select name="assigned_to">
          <option value="">Unassigned</option>
          <?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $t['assigned_to'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <div class="form-actions"><button type="submit" class="btn primary">Update</button></div>
    </form>
  </div>
</div>
