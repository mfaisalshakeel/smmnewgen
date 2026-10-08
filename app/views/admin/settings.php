<?php /** @var array $groups  @var string $cronKey */ ?>
<form method="post" action="<?= e(url('admin/settings')) ?>" enctype="multipart/form-data" class="split2">
  <?= csrf_field() ?>

  <div>
    <?php foreach ($groups as $groupName => $fields): ?>
      <div class="box">
        <div class="box-head"><b><?= e($groupName) ?></b></div>
        <div class="pad">
          <?php foreach ($fields as $key => $field): ?>
            <?php
            $type  = $field['type'] ?? 'text';
            $value = setting($key, '');
            ?>
            <?php if ($type === 'checkbox'): ?>
              <label class="switch block">
                <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>>
                <span class="track"></span> <?= e($field['label']) ?>
              </label>
            <?php else: ?>
              <div class="field">
                <label for="s-<?= e($key) ?>"><?= e($field['label']) ?></label>
                <?php if ($type === 'textarea'): ?>
                  <textarea id="s-<?= e($key) ?>" name="<?= e($key) ?>"
                            rows="<?= (int) ($field['rows'] ?? 4) ?>"><?= e($value) ?></textarea>
                <?php elseif ($type === 'select'): ?>
                  <?php /* A callable builds its list when the page renders, which a
                           list of database rows has to; a fixed list is just a list. */ ?>
                  <?php $options = is_callable($field['options'])
                      ? ($field['options'])() : (array) $field['options']; ?>
                  <select id="s-<?= e($key) ?>" name="<?= e($key) ?>">
                    <?php foreach ($options as $optValue => $optLabel): ?>
                      <option value="<?= e($optValue) ?>"<?= $value === (string) $optValue ? ' selected' : '' ?>>
                        <?= e($optLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <input id="s-<?= e($key) ?>" type="<?= e($type) ?>" name="<?= e($key) ?>"
                         value="<?= e($value) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                <?php endif; ?>
                <?php if (!empty($field['hint'])): ?><small><?= e($field['hint']) ?></small><?php endif; ?>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div>
    <div class="box">
      <div class="box-head"><b>Branding</b></div>
      <div class="pad">
        <?php foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $input => $settingKey): ?>
          <?php $current = setting($settingKey, ''); ?>
          <div class="field">
            <label for="u-<?= e($input) ?>"><?= e(ucfirst($input)) ?></label>
            <?php if ($current): ?>
              <div class="acct" style="display:flex;align-items:center;gap:12px;background:var(--page);
                          border-radius:10px;padding:10px 12px;margin-bottom:9px">
                <img src="<?= e(url($current)) ?>" alt="" style="height:32px;width:auto;border-radius:5px">
                <label class="switch" style="margin-left:auto;font-size:12.5px">
                  <input type="checkbox" name="remove_<?= e($input) ?>" value="1">
                  <span class="track"></span> Remove
                </label>
              </div>
            <?php endif; ?>
            <input id="u-<?= e($input) ?>" type="file" name="<?= e($input) ?>"
                   accept="image/png,image/jpeg,image/gif,image/webp,image/x-icon">
            <small>PNG, JPG, GIF, WebP or ICO. Up to 2 MB.</small>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="box">
      <div class="box-head">
        <b>Database</b>
        <?php if ($pending): ?><span class="st st-pending"><?= count($pending) ?> pending</span><?php endif; ?>
      </div>
      <div class="pad">
        <?php if ($pending): ?>
          <p class="muted" style="margin-bottom:12px">
            This copy of the panel is newer than its database. Apply the outstanding changes:
            <span class="mono"><?= e(implode(', ', $pending)) ?></span>
          </p>
          <form method="post" action="<?= e(url('admin/settings/migrate')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-block" type="submit">Update the database</button>
          </form>
        <?php else: ?>
          <p class="muted">The database is up to date. Nothing to do.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="box">
      <div class="box-head"><b>Cron</b></div>
      <div class="pad">
        <p class="muted" style="margin-bottom:12px">
          Run this every 5 minutes so paid orders are sent and statuses stay fresh.
        </p>
        <div class="field">
          <label>Command (cPanel cron job)</label>
          <div class="copyrow">
            <input value="php <?= e(BASE_PATH) ?>/cron.php" readonly>
            <button class="btn btn-ghost btn-sm" type="button"
                    data-copy="php <?= e(BASE_PATH) ?>/cron.php">Copy</button>
          </div>
        </div>
        <div class="field">
          <label>Or call this URL</label>
          <div class="copyrow">
            <input value="<?= e(url('cron/' . $cronKey)) ?>" readonly>
            <button class="btn btn-ghost btn-sm" type="button"
                    data-copy="<?= e(url('cron/' . $cronKey)) ?>">Copy</button>
          </div>
          <small>The key lives in config/config.php. Anything else under /cron returns 404.</small>
        </div>
      </div>
    </div>

    <div class="box">
      <div class="box-head"><b>Save</b></div>
      <div class="pad">
        <button class="btn btn-primary btn-block btn-lg" type="submit">Save settings</button>
      </div>
    </div>
  </div>
</form>
