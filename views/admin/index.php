<?php
/**
 * Dashboard.
 * @var array $stats  @var array $recent  @var array $providers
 */
$delta = static function (float $now, float $before): array {
    if ($before <= 0) {
        return $now > 0 ? ['up', 'new today'] : ['muted', 'nothing yet'];
    }
    $pct = round((($now - $before) / $before) * 100);
    return [$pct >= 0 ? 'up' : 'warn', ($pct >= 0 ? '+' : '') . $pct . '% vs yesterday'];
};

[$ordersClass, $ordersNote]   = $delta((float) $stats['orders_today'],  (float) $stats['orders_yday']);
[$revenueClass, $revenueNote] = $delta($stats['revenue_today'], $stats['revenue_yday']);
?>

<div class="cards4">
  <div class="scard">
    <div class="si si-a"><svg class="icon"><use href="#i-card"></use></svg></div>
    <div><small>Orders today</small><b><?= qty_fmt($stats['orders_today']) ?></b>
      <span class="<?= e($ordersClass) ?>"><?= e($ordersNote) ?></span></div>
  </div>
  <div class="scard">
    <div class="si si-b"><svg class="icon"><use href="#i-bolt"></use></svg></div>
    <div><small>Revenue today</small><b><?= e(money($stats['revenue_today'])) ?></b>
      <span class="<?= e($revenueClass) ?>"><?= e($revenueNote) ?></span></div>
  </div>
  <div class="scard">
    <div class="si si-c"><svg class="icon"><use href="#i-clock"></use></svg></div>
    <div><small>Awaiting payment</small><b><?= qty_fmt($stats['pending']) ?></b>
      <span class="<?= $stats['pending'] ? 'warn' : 'muted' ?>">
        <?= $stats['pending'] ? 'needs review' : 'all clear' ?></span></div>
  </div>
  <div class="scard">
    <div class="si si-d"><svg class="icon"><use href="#i-users"></use></svg></div>
    <div><small>Active services</small><b><?= qty_fmt($stats['services']) ?></b>
      <span class="muted">across <?= qty_fmt($stats['platforms']) ?> platforms</span></div>
  </div>
</div>

<?php if ($stats['api_errors'] > 0): ?>
  <div class="alert alert-error">
    <?= qty_fmt($stats['api_errors']) ?> order<?= $stats['api_errors'] === 1 ? '' : 's' ?>
    could not be sent to a provider.
    <a href="<?= e(url('admin/orders?status=api_error')) ?>">Review them</a>
  </div>
<?php endif; ?>

<div class="split">
  <div class="box">
    <div class="box-head">
      <b>Recent orders</b>
      <a class="lnk" href="<?= e(url('admin/orders')) ?>">View all &rarr;</a>
    </div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr><th>Code</th><th>Platform</th><th class="hide-sm">Service</th><th>Total</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="5" class="empty">No orders yet. They appear here as soon as customers place them.</td></tr>
        <?php else: foreach ($recent as $order): ?>
          <tr onclick="location.href='<?= e(url('admin/orders/' . $order['id'])) ?>'">
            <td><b><?= e($order['code']) ?></b><small class="sub"><?= e(when($order['created_at'], 'd M, H:i')) ?></small></td>
            <td><?= e($order['platform_name'] ?? '-') ?></td>
            <td class="hide-sm"><?= e(excerpt($order['service_name'], 46)) ?></td>
            <td><?= e(money($order['price'])) ?></td>
            <td><span class="st st-<?= e($order['status']) ?>"><?= e(str_replace('_', ' ', $order['status'])) ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="box">
    <div class="box-head">
      <b>Provider balances</b>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/providers')) ?>">Manage</a>
    </div>
    <div class="provs">
      <?php if (!$providers): ?>
        <p class="muted" style="padding:14px 0">
          No providers yet. <a class="lnk" href="<?= e(url('admin/providers')) ?>">Add your first one</a>
          to start importing services.
        </p>
      <?php else: foreach ($providers as $provider): ?>
        <div class="prov">
          <div class="pv-main">
            <b><?= e($provider['name']) ?></b>
            <small><?= e($provider['api_url']) ?></small>
          </div>
          <div class="pv-bal">
            <b><?= e(money($provider['balance'])) ?></b>
            <span class="st st-<?= $provider['is_active'] ? 'completed' : 'cancelled' ?>">
              <?= $provider['is_active'] ? 'active' : 'off' ?>
            </span>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
