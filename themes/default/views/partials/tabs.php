<?php
/**
 * The category tabs. Real links, swapped in place by the script.
 *
 * The icon comes from `category_icon()`, so a category the admin has never
 * given one still shows the right picture - the name decides it.
 *
 * @var array $categories  @var ?array $category  @var array $platform
 */
?>
<div class="tabs" id="catTabs">
<?php if ($categories): ?>
        <?php foreach ($categories as $c): ?>
          <a class="tab<?= ($category && $c['id'] === $category['id']) ? ' active' : '' ?>"
             href="<?= e(url($platform['slug'] . '/' . $c['slug'])) ?>">
            <svg class="icon"><use href="#<?= e(category_icon($c)) ?>"></use></svg>
            <?= e($c['name']) ?>
          </a>
        <?php endforeach; ?>
<?php endif; ?>
</div>
