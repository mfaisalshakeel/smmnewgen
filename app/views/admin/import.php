<?php
/** Import screen: filter the provider catalogue, tick what you want, import. */
$platformNames = [];
foreach (all('SELECT slug, name FROM platforms') as $p) { $platformNames[$p['slug']] = $p['name']; }
$kindNames = ['followers' => 'Followers', 'likes' => 'Likes', 'views' => 'Views'];
?>
<?php if ($error): ?>
  <div class="alert alert-warning">
    Could not reach <?= e($provider['name']) ?>: <?= e($error) ?>
    <?= $rows ? 'Showing the last catalogue we cached.' : '' ?>
  </div>
<?php endif; ?>

<div class="box">
  <div class="box-head">
    <b>Catalogue &mdash; <?= e($provider['name']) ?></b>
    <span class="muted">
      <?= qty_fmt($total) ?> services
      <?php if ($cachedAt): ?>&middot; cached <?= e(when(date('Y-m-d H:i:s', $cachedAt), 'H:i')) ?><?php endif; ?>
    </span>
  </div>
  <div class="pad">

    <form method="get" action="<?= e(url('admin/import')) ?>" class="irow">
      <div class="field">
        <label for="f-provider">Provider</label>
        <select id="f-provider" name="provider_id" onchange="this.form.submit()">
          <?php foreach ($providers as $candidate): ?>
            <option value="<?= (int) $candidate['id'] ?>"
              <?= (int) $candidate['id'] === (int) $provider['id'] ? 'selected' : '' ?>>
              <?= e($candidate['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="f-q">Search</label>
        <input id="f-q" name="q" value="<?= e($search) ?>" placeholder="e.g. instagram followers">
      </div>
      <div class="field">
        <label for="f-cat">Provider category</label>
        <select id="f-cat" name="category" onchange="this.form.submit()">
          <option value="">All categories</option>
          <?php foreach ($categories as $name): ?>
            <option value="<?= e($name) ?>"<?= $category === $name ? ' selected' : '' ?>><?= e($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="f-markup">Markup %</label>
        <input id="f-markup" type="number" name="markup" value="<?= e($markup) ?>" min="0" max="500"
               onchange="this.form.submit()">
      </div>
      <input type="hidden" name="auto_detect" value="<?= $autoDetect ? '1' : '0' ?>">
      <input type="hidden" name="only_new" value="<?= $onlyNew ? '1' : '0' ?>">
      <button class="btn btn-ghost" type="submit">Apply</button>
    </form>

    <div class="optbar">
      <a class="switch" href="<?= e(url('admin/import?' . http_build_query([
            'provider_id' => $provider['id'], 'q' => $search, 'category' => $category,
            'markup' => $markup, 'auto_detect' => $autoDetect ? '0' : '1',
            'only_new' => $onlyNew ? '1' : '0',
         ]))) ?>">
        <input type="checkbox" <?= $autoDetect ? 'checked' : '' ?> tabindex="-1" aria-hidden="true">
        <span class="track"></span> Auto-detect platform &amp; category
      </a>

      <a class="switch" href="<?= e(url('admin/import?' . http_build_query([
            'provider_id' => $provider['id'], 'q' => $search, 'category' => $category,
            'markup' => $markup, 'auto_detect' => $autoDetect ? '1' : '0',
            'only_new' => $onlyNew ? '0' : '1',
         ]))) ?>">
        <input type="checkbox" <?= $onlyNew ? 'checked' : '' ?> tabindex="-1" aria-hidden="true">
        <span class="track"></span> Hide ones I already have
      </a>

      <form method="post" action="<?= e(url('admin/import/refresh')) ?>" style="margin-left:auto">
        <?= csrf_field() ?>
        <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
        <button class="btn btn-ghost btn-sm" type="submit">Refresh from API</button>
      </form>
    </div>

    <form method="post" action="<?= e(url('admin/import/run')) ?>" id="importForm">
      <?= csrf_field() ?>
      <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
      <input type="hidden" name="markup" value="<?= e($markup) ?>">
      <?php if ($autoDetect): ?><input type="hidden" name="auto_detect" value="1"><?php endif; ?>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th><input type="checkbox" id="checkAll"></th>
              <th>Provider service</th>
              <th class="hide-sm">Platform</th>
              <th class="hide-sm">Category</th>
              <th class="hide-sm">Cost</th>
              <th>Your price</th>
              <th class="hide-sm">Min / Max</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="empty">
              <?= $total ? 'Nothing matches these filters.' : 'This provider returned no services.' ?>
            </td></tr>
          <?php else: foreach ($rows as $row): ?>
            <tr<?= $row['exists'] ? ' class="dim"' : '' ?>>
              <td><input type="checkbox" class="rowchk" name="service[]" value="<?= e($row['id']) ?>"
                         <?= $row['exists'] ? '' : 'checked' ?>></td>
              <td>
                <b><?= e($row['name']) ?></b>
                <small class="sub mono">#<?= e($row['id']) ?> &middot; <?= e($row['category']) ?></small>
              </td>
              <td class="hide-sm">
                <?php if ($row['platform'] && isset($platformNames[$row['platform']])): ?>
                  <span class="chipx"><?= e($platformNames[$row['platform']]) ?></span>
                <?php else: ?><span class="muted">&mdash;</span><?php endif; ?>
              </td>
              <td class="hide-sm">
                <?php if ($row['kind'] && isset($kindNames[$row['kind']])): ?>
                  <span class="chipx"><?= e($kindNames[$row['kind']]) ?></span>
                <?php else: ?><span class="muted">&mdash;</span><?php endif; ?>
              </td>
              <td class="hide-sm"><?= e(money($row['cost'])) ?></td>
              <td><b><?= e(money(round($row['cost'] * (1 + $markup / 100), 2))) ?></b></td>
              <td class="hide-sm mono"><?= qty_fmt($row['min']) ?> / <?= qty_fmt($row['max']) ?></td>
              <td>
                <?php if ($row['exists']): ?>
                  <span class="st st-paid">have it</span>
                <?php else: ?>
                  <span class="st st-completed">new</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="impfoot">
        <label class="switch">
          <input type="checkbox" name="update_existing" value="1" checked>
          <span class="track"></span> Update the ones I already have (otherwise skip them)
        </label>
        <span class="muted" id="impCount">0 selected</span>
        <button class="btn btn-primary" type="submit">Import selected</button>
      </div>
      <p class="muted" style="margin-top:12px">
        Prices include your <?= e($markup) ?>% markup.
        <?= $autoDetect
            ? 'Platform and category are guessed from the service name, then the provider category.'
            : 'Auto-detect is off, so imported services have no platform or category until you set one.' ?>
        Updating keeps any name or description you edited and refreshes cost, price and limits.
      </p>
    </form>
  </div>
</div>

<script>
(function () {
  var form  = document.getElementById('importForm');
  var all   = document.getElementById('checkAll');
  var count = document.getElementById('impCount');

  function boxes()    { return form.querySelectorAll('.rowchk'); }
  function selected() { return form.querySelectorAll('.rowchk:checked').length; }

  function refresh() {
    var n = selected();
    count.textContent = n + ' selected';
    all.checked = n > 0 && n === boxes().length;
  }

  all.addEventListener('change', function () {
    boxes().forEach(function (b) { b.checked = all.checked; });
    refresh();
  });
  form.addEventListener('change', function (e) {
    if (e.target.classList.contains('rowchk')) { refresh(); }
  });
  form.addEventListener('submit', function (e) {
    if (selected() === 0) {
      e.preventDefault();
      alert('Tick at least one service to import.');
    }
  });

  refresh();
})();
</script>
