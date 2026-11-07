<?php
/** @var array $p pagination data from paginate() */
if (($p['pages'] ?? 1) <= 1) {
    return;
}
$query = $_GET;
$link = function (int $page) use ($query) {
    $query['page'] = $page;
    return '?' . http_build_query($query);
};
$start = max(1, $p['page'] - 2);
$end = min($p['pages'], $p['page'] + 2);
?>
<nav class="pagination" aria-label="Pagination">
  <span class="muted plain"><?= (int) $p['total'] ?> records</span>
  <?php if ($p['page'] > 1): ?><a href="<?= e($link($p['page'] - 1)) ?>">‹ Prev</a><?php endif; ?>
  <?php for ($i = $start; $i <= $end; $i++): ?>
    <?php if ($i === $p['page']): ?><span class="current" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e($link($i)) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($p['page'] < $p['pages']): ?><a href="<?= e($link($p['page'] + 1)) ?>">Next ›</a><?php endif; ?>
</nav>
