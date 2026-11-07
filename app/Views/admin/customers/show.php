<?php
$tabs = ['overview' => 'Overview', 'services' => 'Services', 'invoices' => 'Invoices', 'payments' => 'Payments', 'tickets' => 'Tickets', 'notes' => 'Notes', 'activity' => 'Activity'];
$tab = isset($tabs[$tab]) ? $tab : 'overview';
$counts = ['services' => count($services), 'invoices' => count($invoices), 'payments' => count($payments), 'tickets' => count($tickets), 'notes' => count($notes)];
$actions = '<a class="btn ghost" href="' . url('/customers/edit/' . $customer['id']) . '">Edit</a>'
    . '<a class="btn primary" href="' . url('/billing/invoices/create', ['customer_id' => $customer['id']]) . '">New invoice</a>';
?>
<?= view('components.heading', [
    'title' => $customer['business_name'] ?: $customer['full_name'],
    'subtitle' => 'Customer #' . $customer['id'] . ' · joined ' . date_fmt($customer['created_at']),
    'actions' => $actions,
], null) ?>

<div class="stats three">
  <div class="stat"><span>Outstanding balance</span><strong><?= money($balance) ?></strong><small>Unpaid invoices</small></div>
  <div class="stat"><span>Hosting services</span><strong><?= (int) $counts['services'] ?></strong><small>Web and email</small></div>
  <div class="stat"><span>Account status</span><strong class="stat-badge"><?= badge($customer['status']) ?></strong><small><?= $user ? 'Portal login enabled' : 'No portal login' ?></small></div>
</div>

<div class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="<?= url('/customers/view/' . $customer['id'], ['tab' => $key]) ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= e($label) ?><?= isset($counts[$key]) && $counts[$key] ? ' (' . $counts[$key] . ')' : '' ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
  <div class="columns">
    <div class="card">
      <div class="card-head"><h3>Contact details</h3></div>
      <dl class="details">
        <div><dt>Full name</dt><dd><?= e($customer['full_name']) ?></dd></div>
        <div><dt>Business</dt><dd><?= e($customer['business_name']) ?: '—' ?></dd></div>
        <div><dt>Email</dt><dd><a href="mailto:<?= e($customer['email']) ?>"><?= e($customer['email']) ?></a></dd></div>
        <div><dt>Phone</dt><dd><?= e($customer['phone']) ?: '—' ?></dd></div>
        <div><dt>Address</dt><dd><?= e($customer['address']) ?: '—' ?></dd></div>
        <div><dt>City / Country</dt><dd><?= e(trim(($customer['city'] ?? '') . ' ' . ($customer['country'] ?? ''))) ?: '—' ?></dd></div>
      </dl>
      <?php if ($customer['notes']): ?><h4>Staff notes</h4><p class="small prewrap"><?= e($customer['notes']) ?></p><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h3>Account access</h3></div>
      <div class="events">
        <div><b>Client portal <?= $user ? badge('active', 'Enabled') : badge('muted', 'No login') ?></b><small><?= $user ? e($user['email']) . ' · last login ' . datetime_fmt($user['last_login_at']) : 'Create a portal login to give this customer access.' ?></small></div>
        <div><b>Customer account <?= badge($customer['status']) ?></b><small><?= $customer['status'] === 'disabled' ? 'Cannot log in; hosting services are unaffected.' : 'Can log in and manage services.' ?></small></div>
      </div>
      <div class="form-actions">
        <form method="post" action="<?= url('/customers/' . $customer['id'] . '/portal-access') ?>" class="inline" data-confirm="<?= $user ? 'Email a new password set-up link to ' . e($customer['email']) . '?' : 'Create a client portal login for ' . e($customer['email']) . ' and email a password set-up link?' ?>" data-confirm-style="primary">
          <?= csrf_field() ?>
          <button type="submit" class="btn ghost"><?= $user ? 'Resend password link' : 'Create portal login' ?></button>
        </form>
        <form method="post" action="<?= url('/customers/' . $customer['id'] . '/status') ?>" class="inline" data-confirm="<?= $customer['status'] === 'active' ? 'Disable this customer? They will not be able to log in.' : 'Re-enable this customer?' ?>" <?= $customer['status'] === 'active' ? '' : 'data-confirm-style="primary"' ?>>
          <?= csrf_field() ?>
          <button type="submit" class="btn <?= $customer['status'] === 'active' ? 'danger' : 'primary' ?>"><?= $customer['status'] === 'active' ? 'Disable customer' : 'Enable customer' ?></button>
        </form>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'services'): ?>
  <div class="card">
    <div class="card-head"><h3>Hosting services</h3><a class="btn small ghost" href="<?= url('/hosting/create', ['customer_id' => $customer['id']]) ?>">+ Add service</a></div>
    <?php if (!$services): ?><div class="empty">No services yet.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Domain</th><th>Type</th><th>Plan</th><th class="text-right">Price</th><th>Next due</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($services as $s): ?>
        <tr>
          <td><a href="<?= url('/hosting/view/' . $s['id']) ?>"><?= e($s['domain']) ?></a></td>
          <td><?= $s['type'] === 'email' ? 'Email' : 'Web' ?></td>
          <td><?= e($s['plan_name']) ?></td>
          <td class="text-right"><?= money($s['price']) ?> <small class="muted">/ <?= e(cycle_label($s['billing_cycle'])) ?></small></td>
          <td><?= date_fmt($s['next_due_date']) ?></td>
          <td><?= badge($s['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'invoices'): ?>
  <div class="card">
    <div class="card-head"><h3>Invoices</h3><a class="btn small ghost" href="<?= url('/billing/invoices/create', ['customer_id' => $customer['id']]) ?>">+ New invoice</a></div>
    <?php if (!$invoices): ?><div class="empty">No invoices yet.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Invoice</th><th>Issued</th><th>Due</th><th class="text-right">Total</th><th class="text-right">Paid</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($invoices as $i): ?>
        <tr>
          <td><a href="<?= url('/billing/invoices/view/' . $i['id']) ?>"><?= e($i['number']) ?></a></td>
          <td><?= date_fmt($i['issue_date']) ?></td>
          <td><?= date_fmt($i['due_date']) ?></td>
          <td class="text-right"><?= money($i['total'], $i['currency']) ?></td>
          <td class="text-right"><?= money($i['amount_paid'], $i['currency']) ?></td>
          <td><?= badge($i['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'payments'): ?>
  <div class="card">
    <div class="card-head"><h3>Payments</h3></div>
    <?= view('admin.payments._table', ['payments' => $payments], null) ?>
  </div>

<?php elseif ($tab === 'tickets'): ?>
  <div class="card">
    <div class="card-head"><h3>Support tickets</h3><a class="btn small ghost" href="<?= url('/support/create', ['customer_id' => $customer['id']]) ?>">+ Open ticket</a></div>
    <?php if (!$tickets): ?><div class="empty">No tickets.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Ticket</th><th>Subject</th><th>Priority</th><th>Status</th><th>Updated</th></tr></thead>
      <tbody>
      <?php foreach ($tickets as $t): ?>
        <tr>
          <td><a href="<?= url('/support/tickets/' . $t['id']) ?>"><?= e($t['number']) ?></a></td>
          <td class="wrap"><?= e(str_limit($t['subject'], 70)) ?></td>
          <td><?= badge($t['priority']) ?></td>
          <td><?= badge($t['status']) ?></td>
          <td><?= datetime_fmt($t['updated_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'notes'): ?>
  <div class="columns">
    <div class="card">
      <div class="card-head"><h3>Notes</h3></div>
      <?php if (!$notes): ?><div class="empty">No notes yet.</div><?php else: ?>
      <div class="thread">
        <?php foreach ($notes as $n): ?>
          <div class="msg"><header><b><?= e($n['author'] ?: 'Staff') ?></b><small><?= datetime_fmt($n['created_at']) ?></small></header><div class="body"><?= e($n['note']) ?></div></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h3>Add note</h3></div>
      <form method="post" action="<?= url('/customers/' . $customer['id'] . '/notes') ?>">
        <?= csrf_field() ?>
        <label class="field"><span>Note (visible to staff only)</span>
          <textarea name="note" required maxlength="5000"></textarea>
          <?= field_error('note') ?>
        </label>
        <div class="form-actions"><button type="submit" class="btn primary">Add note</button></div>
      </form>
    </div>
  </div>

<?php elseif ($tab === 'activity'): ?>
  <div class="card">
    <div class="card-head"><h3>Activity log</h3></div>
    <?php if (!$activity): ?><div class="empty">No recorded activity.</div><?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Time</th><th>Action</th><th>By</th><th>Details</th></tr></thead>
      <tbody>
      <?php foreach ($activity as $a): ?>
        <tr>
          <td><?= datetime_fmt($a['created_at']) ?></td>
          <td><span class="mono"><?= e($a['action']) ?></span></td>
          <td><?= e($a['user_name'] ?: 'System') ?></td>
          <td class="wrap"><small class="muted"><?= e(str_limit($a['details'] ?? '', 120)) ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
