<?php
/** @var array $series list of ['label' => 'Jan', 'value' => 12.5] */
$max = max(array_merge([0.01], array_column($series, 'value')));
?>
<div class="chart" role="img" aria-label="<?= e($ariaLabel ?? 'Bar chart') ?>">
  <?php foreach ($series as $point): $pct = round(((float) $point['value'] / $max) * 100, 1); ?>
    <div class="barcol"><div class="bar" style="height:<?= max(1.5, $pct) ?>%" data-value="<?= e($point['display'] ?? money($point['value'])) ?>" title="<?= e($point['label'] . ': ' . ($point['display'] ?? money($point['value']))) ?>"></div><small><?= e($point['label']) ?></small></div>
  <?php endforeach; ?>
</div>
