<?php
/**
 * The service cards for the platform and category being shown.
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
        <?php partial('partials/service-card', ['service' => $service, 'platform' => $platform]); ?>
      <?php endforeach; endif; ?>
</div>
