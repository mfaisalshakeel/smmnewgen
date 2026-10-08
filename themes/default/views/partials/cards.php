<?php
/**
 * The service cards for the platform and category being shown.
 *
 * A service sold in preset packages becomes several cards - one per package,
 * then a custom card for any other quantity. A service with no packages is
 * the single card it always was.
 *
 * @var array $services  @var array $platform  @var ?array $category
 */
$noun = $category['name'] ?? 'services';
?>
<div class="cards" id="serviceCards">
      <?php if (!$services): ?>
        <div class="empty-state">
          <b>Nothing here yet</b>
          We are adding <?= e(strtolower($platform['name'] . ' ' . $noun)) ?> services shortly.
          Try another tab above.
        </div>
      <?php else: foreach ($services as $service): ?>
        <?php $packages = service_packages((int) $service['id']); ?>

        <?php if ($packages): ?>
          <?php $GLOBALS['__package_noun'] = $platform['name'] . ' ' . ($category['name'] ?? ''); ?>
          <?php foreach ($packages as $package): ?>
            <?php partial('partials/package-card',
                ['package' => $package, 'service' => $service, 'platform' => $platform]); ?>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php partial('partials/service-card',
            ['service' => $service, 'platform' => $platform, 'custom' => (bool) $packages]); ?>
      <?php endforeach; endif; ?>
</div>
