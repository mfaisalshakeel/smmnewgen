<?php
/**
 * The platform strip.
 *
 * Real links, so the site is crawlable and works without JavaScript. The
 * script intercepts the click and swaps this block in place; the href is what
 * it fetches.
 *
 * @var array $platforms  @var array $platform
 */
?>
<div class="plats" id="platStrip">
        <?php foreach ($platforms as $p): ?>
          <a class="plat<?= $p['id'] === $platform['id'] ? ' active' : '' ?>"
             style="--pc:<?= e($p['color'] ?: '#6c4df6') ?>"
             href="<?= e(url($p['slug'])) ?>">
            <svg class="icon"><use href="#<?= e($p['icon'] ?: 'i-rocket') ?>"></use></svg>
            <?= e($p['name']) ?>
          </a>
        <?php endforeach; ?>
</div>
