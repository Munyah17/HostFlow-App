<?php
$businessName = hostflow_installed() && \App\Core\Database::isConnected() ? setting('business_name') : 'Global Space Web Hosting';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Home') ?> — <?= e($businessName) ?></title>
<link rel="stylesheet" href="<?= asset('css/mockup-base.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
  .public-top{position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 28px;background:#0d1b2a;color:#fff}
  .public-top .brand{display:flex;flex-direction:column;line-height:1.1;color:#fff;text-decoration:none;font-weight:800;font-size:18px}
  .public-top .brand small{font-size:9px;letter-spacing:1.3px;font-weight:500;color:#aabbd4;margin-top:4px}
  .public-top nav{display:flex;gap:18px;align-items:center}
  .public-top nav a{color:#cdd9ea;text-decoration:none;font-size:13px;font-weight:600}
  .public-top nav a:hover{color:#fff}
  .public-main{max-width:1120px;margin:0 auto;padding:32px 22px 64px}
  .public-hero{text-align:center;padding:44px 16px 20px}
  .public-hero h1{font-size:38px;letter-spacing:-.8px;margin:0 0 12px}
  .public-hero p{font-size:16px;color:var(--muted);max-width:600px;margin:0 auto 26px}
  .public-hero .actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
  .steps-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:36px 0 8px}
  .steps-row .step{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px}
  .steps-row .step b{display:block;margin-bottom:6px;font-size:14px}
  .steps-row .step span{color:var(--muted);font-size:12.5px;line-height:1.5}
  .steps-row .num{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#eaf1ff;color:var(--blue);font-weight:800;font-size:12px;margin-bottom:10px}
  .public-foot{border-top:1px solid var(--line);text-align:center;padding:22px;color:var(--muted);font-size:12px}
  @media(max-width:760px){.steps-row{grid-template-columns:repeat(2,1fr)}.public-hero h1{font-size:28px}}
</style>
</head>
<body>
<header class="public-top">
  <a class="brand" href="<?= url('/') ?>">◈ HostFlow<small><?= e(mb_strtoupper((string) $businessName)) ?></small></a>
  <nav>
    <a href="<?= url('/') ?>#plans">Hosting</a>
    <a href="<?= url('/login') ?>">Client sign in</a>
    <a class="btn primary small" href="<?= url('/register') ?>" style="color:#fff">Create account</a>
  </nav>
</header>
<main class="public-main">
  <?= view('components.flash', [], null) ?>
  <?= $content ?>
</main>
<footer class="public-foot"><?= e($businessName) ?> · Powered by HostFlow</footer>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
