<?php
use App\Core\Auth;
use App\Core\Navigation;

$user = Auth::user();
$role = $user['role'] ?? null;
$pageTitle = $title ?? 'Dashboard';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?> — HostFlow · <?= e(setting('business_name')) ?></title>
<link rel="stylesheet" href="<?= asset('css/mockup-base.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<input class="nav-toggle" id="nav-toggle" type="checkbox" aria-hidden="true">
<label class="backdrop" for="nav-toggle" aria-label="Close menu"></label>
<aside class="sidebar" aria-label="Main navigation">
  <div class="brand"><a href="<?= url(Auth::home()) ?>">◈ HostFlow</a><small><?= e(mb_strtoupper((string) setting('business_name'))) ?></small></div>
  <nav class="nav" id="navigation">
    <?php $lastSection = null; ?>
    <?php foreach (Navigation::forRole($role) as $item): ?>
      <?php $section = $item['section'] ?? null; ?>
      <?php if ($section !== $lastSection): $lastSection = $section; ?>
        <?php if ($section): ?><div class="nav-section"><?= e($section) ?></div><?php endif; ?>
      <?php endif; ?>
      <a href="<?= url($item['url']) ?>" class="<?= Navigation::isActive($item) ? 'active' : '' ?>"<?= Navigation::isActive($item) ? ' aria-current="page"' : '' ?>><span class="symbol" aria-hidden="true"><?= icon($item['icon']) ?></span><?= e($item['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sidefoot"><?= e(setting('business_name')) ?><br>HostFlow v<?= e(app_version()) ?></div>
</aside>
<main class="main">
  <header class="topbar">
    <div class="topbar-left">
      <label for="nav-toggle" class="hamburger" aria-label="Toggle navigation menu" role="button" tabindex="0" aria-controls="navigation">☰</label>
      <strong id="breadcrumb"><?= e($pageTitle) ?></strong>
    </div>
    <div class="topright userbox">
      <div class="who"><b><?= e($user['name'] ?? '') ?></b><small><?= e(Auth::ROLE_LABELS[$role] ?? '') ?></small></div>
      <form method="post" action="<?= url('/logout') ?>" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn ghost small" data-no-disable>Sign out</button>
      </form>
    </div>
  </header>
  <div class="content">
    <?= view('components.flash', [], null) ?>
    <?= $content ?>
  </div>
</main>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<div class="modal" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
  <div class="modal-box">
    <h3 id="confirm-title">Please confirm</h3>
    <p data-modal-message></p>
    <label class="field" data-modal-input-wrap style="display:none"><span></span><input type="text" data-modal-input autocomplete="off"></label>
    <div class="form-actions">
      <button type="button" class="btn danger" data-modal-ok>Confirm</button>
      <button type="button" class="btn ghost" data-modal-cancel>Cancel</button>
    </div>
  </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
