<?php
/** Add / edit one service. Manual services simply leave the provider empty. */
$val = static function (string $name, $fallback = '') use ($row) {
    $old = old($name, null);
    return $old !== null ? $old : ($row[$name] ?? $fallback);
};
$err = static fn(string $name) => $errors[$name] ?? null;
?>
<a class="lnk back" href="<?= e(url('admin/services')) ?>">&larr; Back to services</a>

<form method="post" action="<?= e(url('admin/services/save')) ?>" class="split2">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div>
    <div class="box">
      <div class="box-head"><b>What customers see</b></div>
      <div class="pad">
        <div class="field">
          <label for="f-name">Service name <span style="color:var(--danger)">*</span></label>
          <input id="f-name" name="name" value="<?= e($val('name')) ?>" required
                 <?= $err('name') ? 'aria-invalid="true"' : '' ?>
                 placeholder="Instagram Followers - Non Drop">
          <?php if ($err('name')): ?><span class="err"><?= e($err('name')) ?></span><?php endif; ?>
        </div>

        <div class="field">
          <label for="f-description">Short description</label>
          <textarea id="f-description" name="description" rows="3"><?= e($val('description')) ?></textarea>
          <small>One or two lines shown on the service card.</small>
        </div>

        <div class="field2">
          <div class="field">
            <label for="f-badge">Badge</label>
            <input id="f-badge" name="badge" value="<?= e($val('badge')) ?>" placeholder="Best Value">
          </div>
          <div class="field">
            <label for="f-delivery">Delivery time</label>
            <input id="f-delivery" name="delivery_time" value="<?= e($val('delivery_time')) ?>"
                   placeholder="2-15 min">
          </div>
        </div>

        <div class="field">
          <label for="f-features">Feature ticks</label>
          <textarea id="f-features" name="features" rows="3"><?= e($val('features')) ?></textarea>
          <small>One per line. Three works best on the card.</small>
        </div>
      </div>
    </div>

    <div class="box">
      <div class="box-head"><b>Where it appears</b></div>
      <div class="pad">
        <div class="field2">
          <div class="field">
            <label for="f-platform">Platform</label>
            <select id="f-platform" name="platform_id">
              <option value="">Not shown on the site</option>
              <?php foreach ($platforms as $pid => $pname): ?>
                <option value="<?= (int) $pid ?>"<?= (string) $val('platform_id') === (string) $pid ? ' selected' : '' ?>>
                  <?= e($pname) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="f-category">Category</label>
            <select id="f-category" name="category_id">
              <option value="">No category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>" data-platform="<?= (int) $cat['platform_id'] ?>"
                  <?= (string) $val('category_id') === (string) $cat['id'] ? ' selected' : '' ?>>
                  <?= e($cat['platform'] . ' - ' . $cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="f-sort">Sort order</label>
          <input id="f-sort" type="number" name="sort_order" value="<?= e($val('sort_order', 0)) ?>">
          <small>Lower numbers come first inside the category.</small>
        </div>
      </div>
    </div>
  </div>

  <div>
    <div class="box">
      <div class="box-head"><b>Pricing</b></div>
      <div class="pad">
        <div class="field2">
          <div class="field">
            <label for="f-cost">Cost per 1,000</label>
            <input id="f-cost" type="number" step="0.0001" min="0" name="cost_per_1000"
                   value="<?= e($val('cost_per_1000', 0)) ?>">
            <small>What the provider charges you.</small>
          </div>
          <div class="field">
            <label for="f-price">Price per 1,000 <span style="color:var(--danger)">*</span></label>
            <input id="f-price" type="number" step="0.0001" min="0" name="price_per_1000"
                   value="<?= e($val('price_per_1000', 0)) ?>" required
                   <?= $err('price_per_1000') ? 'aria-invalid="true"' : '' ?>>
            <?php if ($err('price_per_1000')): ?>
              <span class="err"><?= e($err('price_per_1000')) ?></span>
            <?php else: ?>
              <small>What the customer pays.</small>
            <?php endif; ?>
          </div>
        </div>

        <div class="field2">
          <div class="field">
            <label for="f-min">Minimum quantity</label>
            <input id="f-min" type="number" min="1" name="min_qty" value="<?= e($val('min_qty', 100)) ?>">
          </div>
          <div class="field">
            <label for="f-max">Maximum quantity</label>
            <input id="f-max" type="number" min="1" name="max_qty" value="<?= e($val('max_qty', 100000)) ?>"
                   <?= $err('max_qty') ? 'aria-invalid="true"' : '' ?>>
            <?php if ($err('max_qty')): ?><span class="err"><?= e($err('max_qty')) ?></span><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="box">
      <div class="box-head"><b>Provider</b></div>
      <div class="pad">
        <div class="field">
          <label for="f-provider">Buy from</label>
          <select id="f-provider" name="provider_id">
            <option value="">Manual - I deliver this myself</option>
            <?php foreach ($providers as $prid => $prname): ?>
              <option value="<?= (int) $prid ?>"<?= (string) $val('provider_id') === (string) $prid ? ' selected' : '' ?>>
                <?= e($prname) ?></option>
            <?php endforeach; ?>
          </select>
          <small>Manual services are never sent to an API; they just move to processing.</small>
        </div>
        <div class="field">
          <label for="f-psid">Provider service id</label>
          <input id="f-psid" name="provider_service_id" value="<?= e($val('provider_service_id')) ?>"
                 <?= $err('provider_service_id') ? 'aria-invalid="true"' : '' ?> placeholder="1042">
          <?php if ($err('provider_service_id')): ?>
            <span class="err"><?= e($err('provider_service_id')) ?></span>
          <?php endif; ?>
        </div>
        <div class="field">
          <label for="f-markup">Markup %</label>
          <input id="f-markup" type="number" step="0.01" min="0" name="markup_percent"
                 value="<?= e($val('markup_percent', 0)) ?>">
          <small>Used when the scheduled sync rebuilds the price from the provider's rate.
                 Leave at 0 to keep whatever margin the current price already has.</small>
        </div>
        <label class="switch block">
          <input type="checkbox" name="auto_sync" value="1" <?= $val('auto_sync', 1) ? 'checked' : '' ?>>
          <span class="track"></span> Keep in step with the provider automatically
        </label>
        <label class="switch block">
          <input type="checkbox" name="supports_refill" value="1" <?= $val('supports_refill') ? 'checked' : '' ?>>
          <span class="track"></span> Supports refill
        </label>
        <label class="switch block">
          <input type="checkbox" name="supports_cancel" value="1" <?= $val('supports_cancel') ? 'checked' : '' ?>>
          <span class="track"></span> Supports cancel
        </label>
      </div>
    </div>

    <div class="box">
      <div class="box-head"><b>Save</b></div>
      <div class="pad">
        <label class="switch block">
          <input type="checkbox" name="is_active" value="1" <?= $val('is_active', 1) ? 'checked' : '' ?>>
          <span class="track"></span> Active (visible to customers)
        </label>
        <label class="switch block">
          <input type="checkbox" name="is_featured" value="1" <?= $val('is_featured') ? 'checked' : '' ?>>
          <span class="track"></span> Highlight as most popular
        </label>
        <button class="btn btn-primary btn-block btn-lg" type="submit" style="margin-top:14px">
          <?= $id ? 'Save changes' : 'Create service' ?>
        </button>
        <a class="btn btn-ghost btn-block" style="margin-top:9px" href="<?= e(url('admin/services')) ?>">Cancel</a>
      </div>
    </div>
  </div>
</form>

<script>
/* Only offer categories that belong to the chosen platform. */
(function () {
  var platform = document.getElementById('f-platform');
  var category = document.getElementById('f-category');
  if (!platform || !category) { return; }

  var options = Array.prototype.slice.call(category.options);

  function sync() {
    var chosen = platform.value;
    options.forEach(function (option) {
      if (!option.value) { return; }
      var belongs = !chosen || option.getAttribute('data-platform') === chosen;
      option.hidden = !belongs;
      option.disabled = !belongs;
      if (!belongs && option.selected) { category.value = ''; }
    });
  }

  platform.addEventListener('change', sync);
  sync();
})();
</script>
