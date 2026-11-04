<div class="card">
  <div class="card-head"><h3>Tasks requiring attention</h3><?php if ($attention): ?><span class="badge suspended"><?= count($attention) ?></span><?php endif; ?></div>
  <?php if (!$attention && !$c['tickets_awaiting'] && !$c['invoices_overdue']): ?>
    <div class="empty">Nothing needs attention right now.</div>
  <?php else: ?>
  <div class="events">
    <?php if ($c['tickets_awaiting']): ?><div><b><a href="<?= url('/support', ['status' => 'open']) ?>"><?= (int) $c['tickets_awaiting'] ?> ticket(s) awaiting a reply</a></b><small>Support queue</small></div><?php endif; ?>
    <?php if ($c['invoices_overdue']): ?><div><b><a href="<?= url('/billing/invoices', ['status' => 'overdue']) ?>"><?= (int) $c['invoices_overdue'] ?> overdue invoice(s) · <?= money($c['overdue_amount']) ?></a></b><small><?= (int) $c['overdue_customers'] ?> customer(s)</small></div><?php endif; ?>
    <?php foreach ($attention as $a): ?>
      <div><b><a href="<?= url('/hosting/view/' . $a['id']) ?>"><?= e($a['reason']) ?> — <?= e($a['domain']) ?></a></b><small><?= e($a['full_name']) ?> · <?= e(str_limit($a['last_error'] ?: $a['sync_message'] ?: status_label($a['status']), 110)) ?></small></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
