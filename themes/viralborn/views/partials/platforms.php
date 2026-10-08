<?php
/**
 * The platform chooser.
 *
 * Real links, so the site is crawlable and works without JavaScript. The
 * script intercepts the click and swaps this block in place; the href is what
 * it fetches, which is why the id has to stay on this element.
 *
 * @var array $platforms  @var array $platform
 */
?>
<div class="plats" id="platStrip">
        <?php foreach ($platforms as $p): ?>
          <a class="plat<?= $p['id'] === $platform['id'] ? ' active' : '' ?>"
             style="--pc:<?= e($p['color'] ?: '#ff1680') ?>"
             href="<?= e(url($p['slug'])) ?>">
            <svg class="icon"><use href="#<?= e($p['icon'] ?: 'i-rocket') ?>"></use></svg>
            <?= e($p['name']) ?>
          </a>
        <?php endforeach; ?>
</div>
