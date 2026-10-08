<?php
/**
 * @var array $rows     categories with their platform
 * @var array $choices  service rows keyed by category id
 * @var bool  $single   whether the catalogue is actually sold this way
 */
?>
<?php if (!$single): ?>
  <div class="alert alert-info">
    The catalogue is set to show <b>every service</b> in a category, so these choices
    are not used yet. Switch to one service per category in
    <a href="<?= e(url('admin/settings')) ?>">Settings</a> to turn them on.
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/mapping/save')) ?>">
  <?= csrf_field() ?>
  <div class="box">
    <div class="box-head">
      <b>Categories</b>
      <span class="muted"><?= qty_fmt(count($rows)) ?> in total</span>
    </div>

    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr><th>Category</th><th>Service shown there</th><th class="ta-r">State</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="3" class="empty">
            No categories yet. Add a platform and a category first.
          </td></tr>
        <?php else: foreach ($rows as $row): ?>
          <?php $options = $choices[(int) $row['id']] ?? []; ?>
          <tr>
            <td style="cursor:default">
              <b><?= e($row['platform']) ?> &middot; <?= e($row['name']) ?></b>
              <small class="sub">/<?= e($row['slug']) ?></small>
            </td>
            <td style="cursor:default">
              <?php if (!$options): ?>
                <span class="muted">No active service in this category yet</span>
              <?php else: ?>
                <select name="service[<?= (int) $row['id'] ?>]" data-search>
                  <option value="0">The first active service</option>
                  <?php foreach ($options as $service): ?>
                    <option value="<?= (int) $service['id'] ?>"
                      <?= (int) $service['id'] === (int) $row['service_id'] ? 'selected' : '' ?>>
                      <?= e(excerpt($service['name'], 60)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </td>
            <td class="ta-r" style="cursor:default">
              <span class="st st-<?= $row['is_active'] ? 'completed' : 'cancelled' ?>">
                <?= $row['is_active'] ? 'active' : 'off' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($rows): ?>
      <div class="pager">
        <span class="muted">A category with no choice shows its first active service.</span>
        <div class="pbtns"><button class="btn btn-primary" type="submit">Save mapping</button></div>
      </div>
    <?php endif; ?>
  </div>
</form>
