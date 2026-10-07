<?php /** @var array $methods  @var array $gateways */ ?>
<div class="alert alert-info">
  A gateway is one file in <span class="mono">app/payments/</span>. Drop a file in and it
  appears in the list below &mdash; nothing else needs changing.
  <?= qty_fmt(count($gateways)) ?> installed:
  <?= e(implode(', ', array_column($gateways, 'name'))) ?>.
</div>

<div class="filters">
  <a class="btn btn-primary" href="<?= e(url('admin/payment-methods/new')) ?>" style="margin-left:auto">
    + Add payment method
  </a>
</div>

<div class="box">
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr><th>Method</th><th class="hide-sm">Gateway</th><th class="hide-sm">Account</th>
            <th>State</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$methods): ?>
        <tr><td colspan="5" class="empty">No payment methods yet. Customers cannot pay until there is one.</td></tr>
      <?php else: foreach ($methods as $method): ?>
        <?php $gateway = $gateways[$method['driver']] ?? null; ?>
        <tr>
          <td>
            <b><?= e($method['name']) ?></b>
            <small class="sub"><?= e($method['account_title'] ?: '-') ?></small>
          </td>
          <td class="hide-sm">
            <?php if ($gateway): ?>
              <span class="chipx"><?= e($gateway['name']) ?></span>
            <?php else: ?>
              <span class="st st-api_error">missing</span>
              <small class="sub mono"><?= e($method['driver']) ?></small>
            <?php endif; ?>
          </td>
          <td class="hide-sm">
            <?php if ($method['account_number'] !== ''): ?>
              <span class="mono"><?= e($method['account_number']) ?></span>
            <?php elseif ($gateway && $gateway['kind'] === 'redirect'): ?>
              <span class="muted">handled by <?= e($gateway['name']) ?></span>
            <?php else: ?>
              <span class="muted">not set</span>
            <?php endif; ?>
          </td>
          <td><span class="st st-<?= $method['is_active'] ? 'completed' : 'cancelled' ?>">
            <?= $method['is_active'] ? 'active' : 'off' ?></span></td>
          <td class="ta-r" style="white-space:nowrap">
            <form method="post" action="<?= e(url('admin/payment-methods/toggle')) ?>" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $method['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit">
                <?= $method['is_active'] ? 'Disable' : 'Enable' ?></button>
            </form>
            <a class="btn btn-ghost btn-sm"
               href="<?= e(url('admin/payment-methods/edit/' . $method['id'])) ?>">Edit</a>
            <form method="post" action="<?= e(url('admin/payment-methods/delete')) ?>" style="display:inline"
                  data-confirm="Delete this payment method?">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $method['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
