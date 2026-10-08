<?php
/**
 * Dashboard.
 *
 * @var array $stats  @var array $recent  @var array $providers
 * @var array $month  @var array $prevMonth  @var array $week  @var array $finish
 * @var array $charts @var array $statuses  @var array $topServices
 * @var array $platformMix  @var array $providerSpend
 */
$delta = static function (float $now, float $before, string $against = 'yesterday'): array {
    if ($before <= 0) {
        // No baseline is not growth; saying so in green would read as one.
        return ['muted', $now > 0 ? 'nothing to compare with' : 'nothing yet'];
    }
    $pct = round((($now - $before) / $before) * 100);
    return [$pct >= 0 ? 'up' : 'warn', ($pct >= 0 ? '+' : '') . $pct . '% vs ' . $against];
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
    <div class="si si-d"><svg class="icon"><use href="#i-up"></use></svg></div>
    <div><small>Profit, 30 days</small><b><?= e(money($month['profit'])) ?></b>
      <span class="<?= $month['margin'] >= 0 ? 'up' : 'warn' ?>">
        <?= e(number_format($month['margin'], 1)) ?>% margin</span></div>
  </div>
</div>

<div class="cards4">
  <div class="scard scard-plain">
    <div><small>Revenue, 30 days</small><b><?= e(money($month['revenue'])) ?></b>
      <?php [$monthClass, $monthNote] = $delta($month['revenue'], $prevMonth['revenue'], 'the 30 before'); ?>
      <span class="<?= e($monthClass) ?>"><?= e($monthNote) ?></span></div>
  </div>
  <div class="scard scard-plain">
    <div><small>Average order</small><b><?= e(money($stats['avg_order'])) ?></b>
      <span class="muted"><?= qty_fmt($month['orders']) ?> orders in 30 days</span></div>
  </div>
  <div class="scard scard-plain">
    <div><small>Completed</small><b><?= e(number_format($finish['rate'], 1)) ?>%</b>
      <span class="<?= $finish['failed'] ? 'warn' : 'muted' ?>">
        <?= qty_fmt($finish['failed']) ?> cancelled, refunded or failed</span></div>
  </div>
  <div class="scard scard-plain">
    <div><small>In progress</small><b><?= qty_fmt($stats['open']) ?></b>
      <span class="muted"><?= qty_fmt($stats['services']) ?> services on
        <?= qty_fmt($stats['platforms']) ?> platforms</span></div>
  </div>
</div>

<div class="charts2">
  <div class="box box-pad"><?= $charts['revenue'] ?></div>
  <div class="box box-pad"><?= $charts['orders'] ?></div>
</div>

<div class="charts3">
  <div class="box box-pad">
    <?= chart_bars(array_map(static function (array $row): array {
          return [
            'label' => str_replace('_', ' ', $row['status']),
            'value' => (float) $row['count'],
            'badge' => '<span class="st st-' . e($row['status']) . '"></span>',
          ];
        }, $statuses), 'Orders by status', static fn(float $v): string => qty_fmt((int) $v)) ?>
  </div>
  <div class="box box-pad">
    <?= chart_bars(array_map(static function (array $row): array {
          return ['label' => excerpt($row['label'], 34), 'value' => (float) $row['revenue'],
                  'note' => qty_fmt((int) $row['orders']) . ' orders'];
        }, $topServices), 'Top services, 30 days', static fn(float $v): string => money($v)) ?>
  </div>
  <div class="box box-pad">
    <?= chart_bars(array_map(static function (array $row): array {
          return ['label' => $row['label'], 'value' => (float) $row['revenue'],
                  'note' => qty_fmt((int) $row['orders']) . ' orders'];
        }, $platformMix), 'Revenue by platform, 30 days', static fn(float $v): string => money($v)) ?>
  </div>
</div>

<div class="charts2">
  <div class="box box-pad"><?= $charts['profit'] ?></div>
  <div class="box box-pad">
    <?= chart_bars(array_map(static function (array $row): array {
          return ['label' => $row['label'], 'value' => (float) $row['cost'],
                  'note' => qty_fmt((int) $row['orders']) . ' orders'];
        }, $providerSpend), 'Provider spend, 30 days', static fn(float $v): string => money($v)) ?>
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
