<?php
/**
 * One service card.
 *
 * The price shown here is only a convenience - the server recalculates it from
 * the service row when the order is placed, so a tampered data attribute buys
 * nothing.
 *
 * @var array $service  @var array $platform
 */
$rate    = (float) $service['price_per_1000'];
$min     = (int) $service['min_qty'];
$max     = (int) $service['max_qty'];
$start   = min($max, max($min, 1000));
$feature = service_feature_lines($service);
?>
<article class="card<?= $service['is_featured'] ? ' featured' : '' ?>"
         data-service="<?= (int) $service['id'] ?>"
         data-rate="<?= e($rate) ?>"
         data-min="<?= $min ?>"
         data-max="<?= $max ?>"
         data-name="<?= e($service['name']) ?>"
         data-label="<?= e($GLOBALS['__package_noun'] ?? $service['name']) ?>"
         data-link-label="<?= e($platform['url_prefix'] ? $platform['name'] . ' link' : 'Profile or post link') ?>"
         data-link-hint="<?= e($platform['url_prefix'] ? 'https://' . $platform['url_prefix'] . '/yourbrand' : 'https://...') ?>">

  <?php if ($service['badge'] !== ''): ?>
    <span class="cbadge"><?= e($service['badge']) ?></span>
  <?php endif; ?>

  <h3><?= e($service['name']) ?></h3>

  <?php if ($service['description'] !== ''): ?>
    <p class="cdesc"><?= e($service['description']) ?></p>
  <?php endif; ?>

  <div class="qp">
    <label class="qp-f"><span>Quantity</span>
      <input type="number" value="<?= $start ?>" min="<?= $min ?>" max="<?= $max ?>"
             inputmode="numeric" data-qty aria-label="Quantity">
    </label>
    <div class="qp-f"><span>Amount</span>
      <div class="price" data-price><?= e(money($start / 1000 * $rate)) ?></div>
    </div>
  </div>
  <p class="qp-note">Price updates instantly according to the quantity you enter</p>
  <p class="qp-err" data-qty-err></p>

  <?php if ($service['delivery_time'] !== ''): ?>
    <div class="qp-del"><svg class="icon"><use href="#i-clock"></use></svg><?= e($service['delivery_time']) ?></div>
  <?php endif; ?>

  <?php if ($feature): ?>
    <ul class="feats">
      <?php foreach (array_slice($feature, 0, 4) as $line): ?>
        <li><span class="tick"><svg class="icon"><use href="#i-check"></use></svg></span><?= e($line) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <button class="btn btn-primary btn-block btn-lg" type="button" data-order>Order Now &rarr;</button>
</article>
