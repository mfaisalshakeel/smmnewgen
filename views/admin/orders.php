<?php /** Orders list. */ ?>
<form class="filters" method="get" action="<?= e(url('admin/orders')) ?>">
  <div class="search wide">
    <svg class="icon"><use href="#i-search"></use></svg>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Order code, link, WhatsApp or transaction id">
  </div>
  <select name="status" onchange="this.form.submit()">
    <option value="">All statuses</option>
    <?php foreach (['pending','paid','processing','completed','partial','cancelled','refunded','api_error'] as $status): ?>
      <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>>
        <?= e(str_replace('_', ' ', $status)) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="platform_id" onchange="this.form.submit()">
    <option value="">All platforms</option>
    <?php foreach ($platforms as $id => $name): ?>
      <option value="<?= (int) $id ?>"<?= $filters['platform_id'] === (string) $id ? ' selected' : '' ?>>
        <?= e($name) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="date" value="<?= e($filters['date']) ?>" onchange="this.form.submit()">
  <button class="btn btn-ghost" type="submit">Search</button>
  <?php if (array_filter($filters, 'strlen')): ?>
    <a class="btn btn-ghost" href="<?= e(url('admin/orders')) ?>">Reset</a>
  <?php endif; ?>
</form>

<div class="box">
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Code</th><th>Platform</th><th class="hide-sm">Service</th><th>Qty</th><th>Total</th>
          <th class="hide-sm">WhatsApp</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="8" class="empty">No orders match these filters.</td></tr>
      <?php else: foreach ($orders as $order): ?>
        <tr onclick="location.href='<?= e(url('admin/orders/' . $order['id'])) ?>'">
          <td><b><?= e($order['code']) ?></b><small class="sub"><?= e(when($order['created_at'], 'd M, H:i')) ?></small></td>
          <td><?= e($order['platform_name'] ?? '-') ?></td>
          <td class="hide-sm"><?= e(excerpt($order['service_name'], 42)) ?></td>
          <td><?= qty_fmt($order['quantity']) ?></td>
          <td><?= e(money($order['price'])) ?></td>
          <td class="hide-sm"><?= e($order['whatsapp']) ?></td>
          <td><span class="st st-<?= e($order['status']) ?>"><?= e(str_replace('_', ' ', $order['status'])) ?></span></td>
          <td class="ta-r">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/orders/' . $order['id'])) ?>"
               onclick="event.stopPropagation()">Open</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pager">
    <span>Showing <?= qty_fmt(count($orders)) ?> of <?= qty_fmt($total) ?> orders</span>
    <?php if ($pages > 1): ?>
      <div class="pbtns">
        <?php
        $link = static function (int $target) use ($filters) {
            return url('admin/orders?' . http_build_query(array_filter($filters, 'strlen') + ['page' => $target]));
        };
        ?>
        <?php if ($page > 1): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e($link($page - 1)) ?>">Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
          <a class="btn btn-ghost btn-sm<?= $p === $page ? ' on' : '' ?>" href="<?= e($link($p)) ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e($link($page + 1)) ?>">Next</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
