<?php
/**
 * The service cards for the platform and category being shown.
 *
 * A service sold in preset packages becomes one card per package. A service
 * with none keeps the single quantity card it always had, so nothing ever
 * disappears from the grid.
 *
 * The services that do have packages then share one custom-quantity panel
 * underneath, rather than each dropping a "custom" card in among the tiers
 * with nothing saying which service it belongs to.
 *
 * @var array $services  @var array $platform  @var ?array $category
 */
$noun   = $category['name'] ?? 'services';
$packed = [];   // services sold in packages, in the order they are shown
?>
<div class="cards" id="serviceCards">
      <?php if (!$services): ?>
        <div class="empty-state">
          <b>Nothing here yet</b>
          We are adding <?= e(strtolower($platform['name'] . ' ' . $noun)) ?> services shortly.
          Try another tab above.
        </div>
      <?php else: ?>
        <?php $GLOBALS['__package_noun'] = trim($platform['name'] . ' ' . ($category['name'] ?? '')); ?>

        <?php foreach ($services as $service): ?>
          <?php $packages = service_packages((int) $service['id']); ?>

          <?php if ($packages): ?>
            <?php $packed[] = $service; ?>
            <?php foreach ($packages as $package): ?>
              <?php partial('partials/package-card',
                  ['package' => $package, 'service' => $service, 'platform' => $platform]); ?>
            <?php endforeach; ?>
          <?php else: ?>
            <?php partial('partials/service-card',
                ['service' => $service, 'platform' => $platform, 'custom' => false]); ?>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($packed): ?>
          <?php partial('partials/custom-order',
              ['services' => $packed, 'platform' => $platform, 'category' => $category]); ?>
        <?php endif; ?>
      <?php endif; ?>
</div>
