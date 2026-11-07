<?php
use App\Core\Auth;
use App\Services\BillingCycle;

$byType = ['web' => [], 'email' => []];
foreach ($plans as $p) {
    $byType[$p['type']][] = $p;
}
$prices = function (array $p): string {
    $parts = [];
    foreach (BillingCycle::LABELS as $cycle => $label) {
        $col = 'price_' . $cycle;
        if ($p[$col] !== null) {
            $parts[] = $label . ' ' . money($p[$col]);
        }
    }
    return implode(' · ', $parts) ?: 'No prices set';
};
?>
<?= view('components.heading', [
    'title' => 'Plans & pricing',
    'subtitle' => count($plans) . ' plans',
    'actions' => (Auth::isSuper() ? '<a class="btn primary" href="' . url('/hosting/plans/create') . '">+ Add plan</a>' : '')
        . (Auth::isSuper() ? '<a class="btn ghost" href="' . url('/hosting/packages') . '">Server packages</a>' : ''),
], null) ?>

<?php foreach (['web' => 'Web hosting', 'email' => 'Email hosting'] as $type => $label): ?>
  <div class="card">
    <div class="card-head"><h3><?= $label ?></h3><span class="muted small"><?= count($byType[$type]) ?> plan(s)</span></div>
    <?php if (!$byType[$type]): ?><div class="empty">No <?= strtolower($label) ?> plans.</div><?php else: ?>
    <div class="plan-grid three">
      <?php foreach ($byType[$type] as $p): ?>
        <div class="plan">
          <div class="plan-top">
            <div><h4><?= e($p['name']) ?></h4><small class="muted"><?= e($p['description'] ?: '') ?></small></div>
            <?= badge($p['status']) ?>
          </div>
          <p class="plan-price"><strong><?= $p['price_monthly'] !== null ? money($p['price_monthly']) . ' <small class="muted">/ mo</small>' : '' ?></strong><br><small class="muted"><?= e($prices($p)) ?></small></p>
          <ul>
            <?php if ($p['storage_gb'] !== null): ?><li><?= e(rtrim(rtrim(number_format((float) $p['storage_gb'], 2), '0'), '.')) ?> GB storage</li><?php endif; ?>
            <?php if ($p['bandwidth_gb'] !== null): ?><li><?= e(rtrim(rtrim(number_format((float) $p['bandwidth_gb'], 2), '0'), '.')) ?> GB bandwidth</li><?php endif; ?>
            <?php if ($p['domains_allowed'] !== null): ?><li><?= (int) $p['domains_allowed'] ?> domain(s)</li><?php endif; ?>
            <?php if ($p['email_accounts'] !== null): ?><li><?= (int) $p['email_accounts'] ?> email account(s)</li><?php endif; ?>
            <?php if ($p['databases_allowed'] !== null): ?><li><?= (int) $p['databases_allowed'] ?> database(s)</li><?php endif; ?>
            <?php foreach (array_filter(array_map('trim', explode("\n", (string) $p['features']))) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
            <li><small class="muted"><?= (int) $p['services'] ?> active service(s)<?= $p['provider_name'] ? ' · ' . e($p['provider_name']) . ($p['provider_package'] ? ' / ' . e($p['provider_package']) : '') : ' · no provider' ?></small></li>
          </ul>
          <?php if (Auth::isSuper()): ?><a class="btn small ghost" href="<?= url('/hosting/plans/edit/' . $p['id']) ?>">Edit</a><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
