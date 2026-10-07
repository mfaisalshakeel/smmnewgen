<?php
/**
 * The category tabs. Real links, swapped in place by the script.
 *
 * @var array $categories  @var ?array $category  @var array $platform
 */
?>
<div class="tabs" id="catTabs">
<?php if ($categories): ?>
        <?php foreach ($categories as $c): ?>
          <a class="tab<?= ($category && $c['id'] === $category['id']) ? ' active' : '' ?>"
             href="<?= e(url($platform['slug'] . '/' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
<?php endif; ?>
</div>
