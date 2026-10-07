<?php
/**
 * Generic add/edit form driven by $spec['fields'].
 * @var array $spec  @var array $row  @var int $id
 */
$errors = crud_errors();
$value  = static function (string $name, $fallback = '') use ($row) {
    $old = old($name, null);
    return $old !== null ? $old : ($row[$name] ?? $fallback);
};
?>
<a class="lnk back" href="<?= e(url($spec['base'])) ?>">&larr; Back to <?= e(strtolower($spec['title'])) ?></a>

<form method="post" action="<?= e(url($spec['base'] . '/save')) ?>" class="split2"
      <?= !empty($spec['upload']) ? 'enctype="multipart/form-data"' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div class="box">
    <div class="box-head"><b><?= e($id ? 'Edit' : 'New') ?> <?= e(strtolower($spec['single'] ?? 'record')) ?></b></div>
    <div class="pad">
      <?php foreach ($spec['fields'] as $name => $field): ?>
        <?php
        $type  = $field['type'] ?? 'text';
        $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
        $error = $errors[$name] ?? null;
        $val   = $value($name, $field['default'] ?? '');
        ?>
        <?php if ($type === 'hidden'): ?>
          <input type="hidden" name="<?= e($name) ?>" value="<?= e($val) ?>">

        <?php elseif ($type === 'checkbox'): ?>
          <label class="switch block">
            <input type="checkbox" name="<?= e($name) ?>" value="1" <?= $val ? 'checked' : '' ?>>
            <span class="track"></span> <?= e($label) ?>
          </label>
          <?php if (!empty($field['hint'])): ?>
            <small class="muted" style="display:block;margin:-6px 0 14px"><?= e($field['hint']) ?></small>
          <?php endif; ?>

        <?php else: ?>
          <div class="field">
            <label for="f-<?= e($name) ?>"><?= e($label) ?>
              <?php if (!empty($field['required'])): ?><span style="color:var(--danger)">*</span><?php endif; ?>
            </label>

            <?php if ($type === 'textarea'): ?>
              <textarea id="f-<?= e($name) ?>" name="<?= e($name) ?>" rows="<?= (int) ($field['rows'] ?? 4) ?>"
                <?= $error ? 'aria-invalid="true"' : '' ?>
                <?= !empty($field['required']) ? 'required' : '' ?>><?= e($val) ?></textarea>

            <?php elseif ($type === 'select'): ?>
              <select id="f-<?= e($name) ?>" name="<?= e($name) ?>" <?= $error ? 'aria-invalid="true"' : '' ?>>
                <?php if (!empty($field['empty'])): ?>
                  <option value=""><?= e($field['empty']) ?></option>
                <?php endif; ?>
                <?php foreach ($field['options'] as $optValue => $optLabel): ?>
                  <option value="<?= e($optValue) ?>" <?= (string) $val === (string) $optValue ? 'selected' : '' ?>>
                    <?= e($optLabel) ?>
                  </option>
                <?php endforeach; ?>
              </select>

            <?php else: ?>
              <input id="f-<?= e($name) ?>" type="<?= e($type) ?>" name="<?= e($name) ?>"
                     value="<?= $type === 'password' ? '' : e($val) ?>"
                     <?= isset($field['step'])  ? 'step="'  . e($field['step'])  . '"' : '' ?>
                     <?= isset($field['min'])   ? 'min="'   . e($field['min'])   . '"' : '' ?>
                     <?= isset($field['max'])   ? 'max="'   . e($field['max'])   . '"' : '' ?>
                     <?= !empty($field['placeholder']) ? 'placeholder="' . e($field['placeholder']) . '"' : '' ?>
                     <?= !empty($field['required']) && empty($field['skip_empty']) ? 'required' : '' ?>
                     <?= $error ? 'aria-invalid="true"' : '' ?>
                     autocomplete="<?= e($field['autocomplete'] ?? 'off') ?>">
            <?php endif; ?>

            <?php if ($error): ?>
              <span class="err"><?= e($error) ?></span>
            <?php elseif (!empty($field['hint'])): ?>
              <small><?= e($field['hint']) ?></small>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="box">
      <div class="box-head"><b>Save</b></div>
      <div class="pad">
        <button class="btn btn-primary btn-block btn-lg" type="submit">
          <?= e($id ? 'Save changes' : 'Create') ?>
        </button>
        <a class="btn btn-ghost btn-block" style="margin-top:9px"
           href="<?= e(url($spec['base'])) ?>">Cancel</a>
      </div>
    </div>
    <?php if (!empty($spec['form_note'])): ?>
      <div class="box"><div class="pad"><p class="muted"><?= e($spec['form_note']) ?></p></div></div>
    <?php endif; ?>
  </div>
</form>
