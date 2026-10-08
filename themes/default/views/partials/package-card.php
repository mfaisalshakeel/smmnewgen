<?php
/**
 * One preset package.
 *
 * The price shown is only a convenience. A real package is read back from its
 * own row when the order is placed; a generated tier carries no id, so the
 * server prices it from the service's rate - the same number the card showed.
 * Either way a tampered data attribute buys nothing.
 *
 * @var array $package  @var array $service  @var array $platform
 */
$delivered = (int) $package['quantity'] + (int) $package['bonus_quantity'];
$feature   = array_values(array_filter(array_map('trim', explode("\n", (string) $service['features']))));
$noun      = $GLOBALS['__package_noun'] ?? $service['name'];
?>
<article class="card pkg<?= $package['badge'] !== '' ? ' pkg-badged' : '' ?>"
         data-service="<?= (int) $service['id'] ?>"
         data-package="<?= $package['id'] ? (int) $package['id'] : '' ?>"
         data-qty="<?= $delivered ?>"
         data-price="<?= e(money($package['price'])) ?>"
         data-name="<?= e($service['name']) ?>"
         data-link-label="<?= e($platform['url_prefix'] ? $platform['name'] . ' link' : 'Profile or post link') ?>"
         data-link-hint="<?= e($platform['url_prefix'] ? 'https://' . $platform['url_prefix'] . '/yourbrand' : 'https://...') ?>">

  <?php if ($package['badge'] !== ''): ?>
    <span class="pkg-badge"><?= e($package['badge']) ?></span>
  <?php endif; ?>

  <div class="pkg-head">
    <b><?= e($noun) ?></b>
    <span class="pkg-qty"><?= e(qty_fmt($package['quantity'])) ?></span>
  </div>

  <div class="pkg-body">
    <p class="pkg-line">
      <?= e(qty_fmt($package['quantity'])) ?><?php if ($package['bonus_quantity'] > 0): ?>
        + <?= e(qty_fmt($package['bonus_quantity'])) ?> EXTRA<?php endif; ?>
      <?= e($noun) ?>
    </p>

    <div class="pkg-price"><?= e(money($package['price'])) ?></div>

    <?php if ($feature): ?>
      <ul class="feats">
        <?php foreach (array_slice($feature, 0, 4) as $line): ?>
          <li><span class="tick"><svg class="icon"><use href="#i-check"></use></svg></span><?= e($line) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($service['delivery_time'] !== ''): ?>
      <div class="qp-del"><svg class="icon"><use href="#i-clock"></use></svg><?= e($service['delivery_time']) ?></div>
    <?php endif; ?>

    <button class="btn btn-primary btn-block btn-lg" type="button" data-order>Order Now &rarr;</button>
  </div>
</article>
