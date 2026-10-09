<?php
/**
 * The custom-quantity panel, below the packages.
 *
 * Packages answer "how much for 1,000?"; this answers "how much for the
 * number I actually want?". It is one panel for the whole category rather
 * than one per service, because several services share a category and a
 * customer should be choosing between them in one place - which is also the
 * only honest way to ask which service a custom quantity belongs to.
 *
 * It is a `.card` with the same data attributes as any other, so the price
 * maths and the order modal work on it unchanged; picking a different
 * service rewrites those attributes rather than re-rendering anything.
 *
 * @var array $services  @var array $platform  @var ?array $category
 */
$first = $services[0];
$rate  = (float) $first['price_per_1000'];
$min   = (int) $first['min_qty'];
$max   = (int) $first['max_qty'];
$start = min($max, max($min, 1000));

$linkLabel = $platform['url_prefix'] ? $platform['name'] . ' link' : 'Profile or post link';
$linkHint  = $platform['url_prefix'] ? 'https://' . $platform['url_prefix'] . '/yourbrand' : 'https://...';
?>
<div class="customwrap">
  <div class="card custom"
       data-service="<?= (int) $first['id'] ?>"
       data-rate="<?= e($rate) ?>"
       data-min="<?= $min ?>"
       data-max="<?= $max ?>"
       data-name="<?= e($first['name']) ?>"
       data-label="<?= e(trim($platform['name'] . ' ' . ($category['name'] ?? ''))) ?>"
       data-link-label="<?= e($linkLabel) ?>"
       data-link-hint="<?= e($linkHint) ?>">

    <h3>Custom <?= e(strtolower($category['name'] ?? 'order')) ?></h3>
    <p class="cdesc">Want a number that is not on a package? Type it here.</p>

    <?php if (count($services) > 1): ?>
      <label class="pickf">
        <span>Service</span>
        <select data-service-pick>
          <?php foreach ($services as $service): ?>
            <option value="<?= (int) $service['id'] ?>"
                    data-rate="<?= e($service['price_per_1000']) ?>"
                    data-min="<?= (int) $service['min_qty'] ?>"
                    data-max="<?= (int) $service['max_qty'] ?>"
                    data-name="<?= e($service['name']) ?>"
                    data-delivery="<?= e($service['delivery_time']) ?>">
              <?= e($service['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
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
    <p class="qp-range">Between <span data-range-min><?= e(qty_fmt($min)) ?></span>
       and <span data-range-max><?= e(qty_fmt($max)) ?></span></p>

    <div class="qp-del"<?= $first['delivery_time'] === '' ? ' hidden' : '' ?>>
      <svg class="icon"><use href="#i-clock"></use></svg>
      <span data-delivery-text><?= e($first['delivery_time']) ?></span>
    </div>

    <button class="btn btn-primary btn-block btn-lg" type="button" data-order>Order Now &rarr;</button>
  </div>
</div>
