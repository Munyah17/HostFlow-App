<?= view('components.heading', [
    'title' => $t['number'] . ' · ' . $t['subject'],
    'subtitle' => $t['category'] . ' · opened ' . datetime_fmt($t['created_at']) . ($t['domain'] ? ' · ' . $t['domain'] : ''),
    'actions' => '<a class="btn ghost" href="' . url('/client/support') . '">All tickets</a>',
], null) ?>

<div class="card card-mid">
  <div class="card-head"><h3>Conversation</h3><span><?= badge($t['status']) ?> <?= badge($t['priority']) ?></span></div>
  <div class="thread">
    <?php foreach ($messages as $m): ?>
      <div class="msg <?= $m['role'] === 'client' ? '' : 'staff' ?>">
        <header><b><?= e($m['name'] ?: 'You') ?><?= $m['role'] !== 'client' ? ' <small class="muted">(staff)</small>' : '' ?></b><small><?= datetime_fmt($m['created_at']) ?></small></header>
        <div class="body"><?= e($m['message']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($t['status'] !== 'closed'): ?>
    <h4>Reply</h4>
    <form method="post" action="<?= url('/client/support/' . $t['id'] . '/reply') ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Message</span><textarea name="message" required maxlength="20000"></textarea><?= field_error('message') ?></label>
      <div class="form-actions"><button type="submit" class="btn primary">Send reply</button></div>
    </form>
  <?php else: ?>
    <div class="empty">This ticket is closed. <a href="<?= url('/client/support/create') ?>">Open a new ticket</a> if you need more help.</div>
  <?php endif; ?>
</div>
