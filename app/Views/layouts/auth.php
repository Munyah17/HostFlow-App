<?php
$businessName = hostflow_installed() && \App\Core\Database::isConnected() ? setting('business_name') : 'Global Space Web Hosting';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Sign in') ?> — HostFlow</title>
<link rel="stylesheet" href="<?= asset('css/mockup-base.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-outer" style="max-width:<?= !empty($wide) ? '720px' : '440px' ?>">
    <div class="auth-brand">◈ HostFlow<small><?= e(mb_strtoupper((string) $businessName)) ?></small></div>
    <div class="auth-card<?= !empty($wide) ? ' wide' : '' ?>">
      <?= view('components.flash', [], null) ?>
      <?= $content ?>
    </div>
  </div>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
