<?php foreach (\App\Core\Session::flashes() as $type => $message): ?>
  <div class="alert <?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($message) ?></div>
<?php endforeach; ?>
