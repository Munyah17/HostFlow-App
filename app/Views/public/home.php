<?php
$web = array_filter($plans, fn ($p) => $p['type'] === 'web');
$email = array_filter($plans, fn ($p) => $p['type'] === 'email');
$from = function (array $p): ?array {
    foreach (['monthly' => 'price_monthly', 'quarterly' => 'price_quarterly', 'semiannual' => 'price_semiannual', 'annual' => 'price_annual'] as $cycle => $col) {
        if ($p[$col] !== null) {
            return ['cycle' => $cycle, 'price' => (float) $p[$col]];
        }
    }
    return null;
};
$fromLabel = function (array $f): string {
    return $f['cycle'] === 'monthly' ? money($f['price']) . '<small class="muted">/mo</small>' : money($f['price']) . '<small class="muted">/' . e(str_replace('ly', '', cycle_label($f['cycle']))) . '</small>';
};
?>

<div class="public-hero">
  <h1>Fast, reliable hosting<br>for your business</h1>
  <p><?= e(setting('business_name')) ?> — cPanel hosting with instant activation, secure online payments and automatic renewals.</p>
  <div class="actions"><a class="btn primary" href="#plans">View plans</a><a class="btn ghost" href="<?= url('/login') ?>">Client area</a></div>
</div>

<div class="steps-row">
  <div class="step"><span class="num">1</span><b>Choose a plan</b><span>Pick the hosting package that fits your project.</span></div>
  <div class="step"><span class="num">2</span><b>Pay securely</b><span>Pay online instantly by card or mobile money.</span></div>
  <div class="step"><span class="num">3</span><b>Instant activation</b><span>Your cPanel account is created automatically as soon as payment clears.</span></div>
  <div class="step"><span class="num">4</span><b>Stay online</b><span>Renewal invoices are generated automatically — never lose your site.</span></div>
</div>

<div id="plans"></div>
<?php if (!$plans): ?>
  <div class="card"><div class="empty">Hosting plans will be listed here soon. Please check back.</div></div>
<?php endif; ?>
<?php foreach ([['web', 'Web hosting', $web], ['email', 'Email hosting', $email]] as [$type, $label, $list]): ?>
  <?php if (!$list) { continue; } ?>
  <h2 class="section-h"><?= e($label) ?></h2>
  <div class="plan-grid<?= count($list) >= 3 ? ' three' : '' ?>">
    <?php foreach ($list as $p): ?>
      <?php $f = $from($p); ?>
      <div class="plan">
        <div class="plan-top"><div><h4><?= e($p['name']) ?></h4><small class="muted"><?= $type === 'email' ? 'Email hosting' : 'Web hosting' ?></small></div><strong><?= $f ? $fromLabel($f) : '' ?></strong></div>
        <?php if ($p['description']): ?><p class="small muted my-8"><?= e($p['description']) ?></p><?php endif; ?>
        <ul>
          <?php if ($p['storage_gb'] !== null): ?><li><?= e(rtrim(rtrim(number_format((float) $p['storage_gb'], 2), '0'), '.')) ?> GB storage</li><?php endif; ?>
          <?php if ($p['bandwidth_gb'] !== null): ?><li><?= e(rtrim(rtrim(number_format((float) $p['bandwidth_gb'], 2), '0'), '.')) ?> GB bandwidth</li><?php endif; ?>
          <?php if ($p['email_accounts'] !== null): ?><li><?= (int) $p['email_accounts'] ?> email account(s)</li><?php endif; ?>
          <?php foreach (array_slice(array_filter(array_map('trim', explode("\n", (string) $p['features']))), 0, 4) as $feat): ?><li><?= e($feat) ?></li><?php endforeach; ?>
        </ul>
        <a class="btn primary btn-block" href="<?= url('/order/' . (int) $p['id']) ?>">Order now</a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
