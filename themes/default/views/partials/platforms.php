<?php
/**
 * The platform strip.
 *
 * Real links, so the site is crawlable and works without JavaScript. The
 * script intercepts the click and swaps this block in place; the href is what
 * it fetches.
 *
 * The logo alone: Instagram, TikTok and YouTube are known by their marks, the
 * name under every one of them said what the picture already said, and the
 * card below names the platform anyway. The name is still on the link for a
 * screen reader and as a tooltip - and it is printed when a platform has no
 * icon of its own, because a row of identical default glyphs chooses nothing.
 *
 * @var array $platforms  @var array $platform
 */
?>
<div class="plats" id="platStrip">
        <?php foreach ($platforms as $p): ?>
          <?php $icon = trim((string) $p['icon']); ?>
          <a class="plat<?= $p['id'] === $platform['id'] ? ' active' : '' ?><?= $icon !== '' ? ' plat-icon' : '' ?>"
             style="--pc:<?= e($p['color'] ?: '#6c4df6') ?>"
             href="<?= e(url($p['slug'])) ?>"
             title="<?= e($p['name']) ?>" aria-label="<?= e($p['name']) ?>">
            <svg class="icon"><use href="#<?= e($icon ?: 'i-rocket') ?>"></use></svg>
            <?php if ($icon === ''): ?><?= e($p['name']) ?><?php endif; ?>
          </a>
        <?php endforeach; ?>
</div>
