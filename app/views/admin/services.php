<?php
/** Services list with filters and bulk actions. */
$query = http_build_query(array_filter($filters, 'strlen'));
?>
<form class="filters" method="get" action="<?= e(url('admin/services')) ?>">
  <div class="search wide">
    <svg class="icon"><use href="#i-search"></use></svg>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Search service name or provider id">
  </div>
  <select name="platform_id" onchange="this.form.submit()">
    <option value="">All platforms</option>
    <?php foreach ($platforms as $id => $name): ?>
      <option value="<?= (int) $id ?>"<?= $filters['platform_id'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="provider_id" onchange="this.form.submit()">
    <option value="">All providers</option>
    <?php foreach ($providers as $id => $name): ?>
      <option value="<?= (int) $id ?>"<?= $filters['provider_id'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="state" onchange="this.form.submit()">
    <option value="">Active &amp; inactive</option>
    <option value="active"<?= $filters['state'] === 'active' ? ' selected' : '' ?>>Active only</option>
    <option value="inactive"<?= $filters['state'] === 'inactive' ? ' selected' : '' ?>>Inactive only</option>
    <option value="manual"<?= $filters['state'] === 'manual' ? ' selected' : '' ?>>Manual (no provider)</option>
  </select>
  <?php if ($query !== ''): ?>
    <a class="btn btn-ghost" href="<?= e(url('admin/services')) ?>">Reset</a>
  <?php endif; ?>
  <a class="btn btn-ghost" href="<?= e(url('admin/import')) ?>" style="margin-left:auto">Import from provider</a>
  <a class="btn btn-primary" href="<?= e(url('admin/services/new')) ?>">+ Add service</a>
</form>

<form method="post" action="<?= e(url('admin/services/bulk')) ?>" id="bulkForm">
  <?= csrf_field() ?>
  <input type="hidden" name="return" value="<?= e($query) ?>">

  <div class="bulkbar" id="bulkBar">
    <b><span id="selCount">0</span> selected</b>
    <button class="btn btn-ghost btn-sm" type="submit" name="bulk_action" value="activate">Activate</button>
    <button class="btn btn-ghost btn-sm" type="submit" name="bulk_action" value="deactivate">Deactivate</button>
    <button class="btn btn-ghost btn-sm" type="submit" name="bulk_action" value="feature">Toggle popular</button>
    <span class="markup">
      <span class="mk">
        <input type="number" name="bulk_markup" value="<?= e(setting('default_markup', '35')) ?>" min="0" max="500">
        <span>%</span>
      </span>
      <button class="btn btn-ghost btn-sm" type="submit" name="bulk_action" value="markup">Apply markup</button>
    </span>
    <button class="btn btn-ghost btn-sm" type="submit" name="bulk_action" value="sync">Sync prices</button>
    <button class="btn btn-danger btn-sm" type="submit" name="bulk_action" value="delete"
            data-bulk-confirm="Delete the selected services? Ones with orders are deactivated instead.">Delete</button>
  </div>

  <div class="box">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th><input type="checkbox" id="checkAll"></th>
            <th>Service</th>
            <th class="hide-sm">Provider</th>
            <th class="hide-sm">Cost</th>
            <th>Price</th>
            <th class="hide-sm">Min / Max</th>
            <th>State</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$services): ?>
          <tr><td colspan="8" class="empty">
            No services match these filters.
            <a class="lnk" href="<?= e(url('admin/import')) ?>">Import some from a provider</a>
            or add one by hand.
          </td></tr>
        <?php else: foreach ($services as $service): ?>
          <tr>
            <td><input type="checkbox" class="rowchk" name="ids[]" value="<?= (int) $service['id'] ?>"></td>
            <td>
              <b><?= e($service['name']) ?></b>
              <?php if ($service['is_featured']): ?>
                <span class="chipx">popular</span>
              <?php endif; ?>
              <small class="sub">
                <?= e($service['platform_name'] ?? 'no platform') ?> &middot;
                <?= e($service['category_name'] ?? 'no category') ?>
              </small>
            </td>
            <td class="hide-sm">
              <?php if ($service['provider_name']): ?>
                <?= e($service['provider_name']) ?>
                <small class="sub mono">#<?= e($service['provider_service_id']) ?></small>
              <?php else: ?>
                <span class="muted">manual</span>
              <?php endif; ?>
            </td>
            <td class="hide-sm"><?= e(money($service['cost_per_1000'])) ?></td>
            <td>
              <b><?= e(money($service['price_per_1000'])) ?></b>
              <?php
              $cost = (float) $service['cost_per_1000'];
              if ($cost > 0):
                  $margin = round((((float) $service['price_per_1000'] - $cost) / $cost) * 100);
              ?>
                <small class="sub"><?= $margin >= 0 ? '+' : '' ?><?= (int) $margin ?>%</small>
              <?php endif; ?>
            </td>
            <td class="hide-sm mono"><?= qty_fmt($service['min_qty']) ?> / <?= qty_fmt($service['max_qty']) ?></td>
            <td><span class="st st-<?= $service['is_active'] ? 'completed' : 'cancelled' ?>">
              <?= $service['is_active'] ? 'active' : 'off' ?></span></td>
            <td class="ta-r" style="white-space:nowrap">
              <a class="btn btn-ghost btn-sm"
                 href="<?= e(url('admin/packages?service_id=' . $service['id'])) ?>">Packages</a>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/services/edit/' . $service['id'])) ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($services): ?>
      <div class="pager"><span><?= qty_fmt(count($services)) ?> service<?= count($services) === 1 ? '' : 's' ?></span></div>
    <?php endif; ?>
  </div>
</form>

<script>
(function () {
  var form  = document.getElementById('bulkForm');
  var all   = document.getElementById('checkAll');
  var bar   = document.getElementById('bulkBar');
  var count = document.getElementById('selCount');

  function boxes() { return form.querySelectorAll('.rowchk'); }
  function selected() { return form.querySelectorAll('.rowchk:checked').length; }

  function refresh() {
    var n = selected();
    count.textContent = n;
    bar.classList.toggle('on', n > 0);
    all.checked = n > 0 && n === boxes().length;
  }

  all.addEventListener('change', function () {
    boxes().forEach(function (b) { b.checked = all.checked; });
    refresh();
  });
  form.addEventListener('change', function (e) {
    if (e.target.classList.contains('rowchk')) { refresh(); }
  });

  // Confirm only the destructive button, and only once something is selected.
  form.addEventListener('submit', function (e) {
    if (selected() === 0) {
      e.preventDefault();
      alert('Select at least one service first.');
      return;
    }
    var btn = e.submitter;
    var message = btn && btn.getAttribute('data-bulk-confirm');
    if (message && !window.confirm(message)) { e.preventDefault(); }
  });

  refresh();
})();
</script>
