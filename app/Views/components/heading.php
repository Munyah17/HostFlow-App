<div class="heading">
  <div>
    <h1><?= e($title) ?></h1>
    <?php if (!empty($subtitle)): ?><p><?= e($subtitle) ?></p><?php endif; ?>
  </div>
  <?php if (!empty($actions)): ?><div class="actions"><?= $actions ?></div><?php endif; ?>
</div>
