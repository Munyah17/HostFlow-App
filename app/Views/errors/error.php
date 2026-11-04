<?php if (\App\Core\Auth::check()): ?>
<?= view('components.heading', ['title' => $title], null) ?>
<div class="card">
  <p><?= e($message) ?></p>
  <a class="btn" href="<?= url(\App\Core\Auth::home()) ?>">Back to dashboard</a>
</div>
<?php else: ?>
<h1><?= e($title) ?></h1>
<p class="sub"><?= e($message) ?></p>
<a class="btn primary" href="<?= url('/login') ?>">Go to sign in</a>
<?php endif; ?>
