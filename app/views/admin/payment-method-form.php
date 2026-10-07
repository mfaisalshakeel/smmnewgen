<?php
/**
 * One payment method. The gateway's own fields are rendered from its manifest,
 * so a new gateway needs no change here.
 *
 * @var array $row  @var int $id  @var array $config  @var array $gateways  @var array $errors
 */
$val = static function (string $name, $fallback = '') use ($row) {
    $old = old($name, null);
    return $old !== null ? $old : ($row[$name] ?? $fallback);
};
$err     = static fn(string $name) => $errors[$name] ?? null;
$current = (string) $val('driver', 'manual');
?>
<a class="lnk back" href="<?= e(url('admin/payment-methods')) ?>">&larr; Back to payment methods</a>

<form method="post" action="<?= e(url('admin/payment-methods/save')) ?>" class="split2">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div>
    <div class="box">
      <div class="box-head"><b>Gateway</b></div>
      <div class="pad">
        <div class="choice">
          <?php foreach ($gateways as $key => $gateway): ?>
            <label class="opt<?= $key === $current ? ' on' : '' ?>">
              <input type="radio" name="driver" value="<?= e($key) ?>"
                     <?= $key === $current ? 'checked' : '' ?> data-driver>
              <b><?= e($gateway['name']) ?></b>
              <small><?= e($gateway['blurb']) ?></small>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php foreach ($gateways as $key => $gateway): ?>
      <?php if (!$gateway['fields']) { continue; } ?>
      <div class="box" data-for-driver="<?= e($key) ?>">
        <div class="box-head"><b><?= e($gateway['name']) ?> settings</b></div>
        <div class="pad">
          <?php foreach ($gateway['fields'] as $fieldKey => $field): ?>
            <?php $name = 'cfg_' . $fieldKey; ?>
            <div class="field">
              <label for="f-<?= e($name) ?>"><?= e($field['label'] ?? $fieldKey) ?>
                <?php if (!empty($field['required'])): ?><span style="color:var(--danger)">*</span><?php endif; ?>
              </label>
              <input id="f-<?= e($name) ?>" name="<?= e($name) ?>"
                     type="<?= e($field['type'] ?? 'text') ?>"
                     value="<?= ($field['type'] ?? 'text') === 'password' ? '' : e($config[$fieldKey] ?? ($field['default'] ?? '')) ?>"
                     <?= !empty($field['placeholder']) ? 'placeholder="' . e($field['placeholder']) . '"' : '' ?>
                     <?= $err($name) ? 'aria-invalid="true"' : '' ?> autocomplete="off">
              <?php if ($err($name)): ?>
                <span class="err"><?= e($err($name)) ?></span>
              <?php elseif (!empty($field['hint'])): ?>
                <small><?= e($field['hint']) ?></small>
              <?php elseif (($field['type'] ?? '') === 'password' && $id): ?>
                <small>Leave empty to keep the saved value.</small>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="box" data-manual-only>
      <div class="box-head"><b>Account details</b></div>
      <div class="pad">
        <div class="field">
          <label for="f-account_title">Account title</label>
          <input id="f-account_title" name="account_title" value="<?= e($val('account_title')) ?>">
        </div>
        <div class="field">
          <label for="f-account_number">Account number / IBAN</label>
          <input id="f-account_number" name="account_number" value="<?= e($val('account_number')) ?>"
                 <?= $err('account_number') ? 'aria-invalid="true"' : '' ?>>
          <?php if ($err('account_number')): ?>
            <span class="err"><?= e($err('account_number')) ?></span>
          <?php endif; ?>
        </div>
        <div class="field2">
          <div class="field">
            <label for="f-extra_label">Extra field label</label>
            <input id="f-extra_label" name="extra_label" value="<?= e($val('extra_label')) ?>" placeholder="Branch">
          </div>
          <div class="field">
            <label for="f-extra_value">Extra field value</label>
            <input id="f-extra_value" name="extra_value" value="<?= e($val('extra_value')) ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div>
    <div class="box">
      <div class="box-head"><b>How it looks</b></div>
      <div class="pad">
        <div class="field">
          <label for="f-name">Name <span style="color:var(--danger)">*</span></label>
          <input id="f-name" name="name" value="<?= e($val('name')) ?>" required
                 <?= $err('name') ? 'aria-invalid="true"' : '' ?> placeholder="JazzCash">
          <?php if ($err('name')): ?><span class="err"><?= e($err('name')) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="f-short_name">Short code</label>
          <input id="f-short_name" name="short_name" value="<?= e($val('short_name')) ?>" placeholder="JC">
          <small>Two letters for the little badge.</small>
        </div>
        <div class="field">
          <label for="f-instructions">Instructions</label>
          <textarea id="f-instructions" name="instructions" rows="4"><?= e($val('instructions')) ?></textarea>
          <small>Shown under the details on the payment page.</small>
        </div>
        <div class="field">
          <label for="f-sort">Sort order</label>
          <input id="f-sort" type="number" name="sort_order" value="<?= e($val('sort_order', 0)) ?>">
        </div>
      </div>
    </div>

    <div class="box">
      <div class="box-head"><b>Save</b></div>
      <div class="pad">
        <label class="switch block">
          <input type="checkbox" name="is_active" value="1" <?= $val('is_active', 1) ? 'checked' : '' ?>>
          <span class="track"></span> Offer this to customers
        </label>
        <button class="btn btn-primary btn-block btn-lg" type="submit" style="margin-top:12px">
          <?= $id ? 'Save changes' : 'Create' ?>
        </button>
        <a class="btn btn-ghost btn-block" style="margin-top:9px"
           href="<?= e(url('admin/payment-methods')) ?>">Cancel</a>
      </div>
    </div>
  </div>
</form>

<script>
/* Show only the chosen gateway's settings. The server reads what it needs
   either way, so this is only tidiness. */
(function () {
  var manualKinds = <?= json_encode(array_keys(array_filter($gateways, fn($g) => $g['kind'] === 'manual'))) ?>;

  function sync() {
    var picked = document.querySelector('[data-driver]:checked');
    picked = picked ? picked.value : 'manual';

    document.querySelectorAll('[data-for-driver]').forEach(function (block) {
      block.style.display = block.getAttribute('data-for-driver') === picked ? '' : 'none';
    });
    document.querySelectorAll('[data-manual-only]').forEach(function (block) {
      block.style.display = manualKinds.indexOf(picked) > -1 ? '' : 'none';
    });
    document.querySelectorAll('.opt').forEach(function (opt) {
      opt.classList.toggle('on', opt.querySelector('input').value === picked);
    });
  }

  document.querySelectorAll('[data-driver]').forEach(function (r) {
    r.addEventListener('change', sync);
  });
  sync();
})();
</script>
