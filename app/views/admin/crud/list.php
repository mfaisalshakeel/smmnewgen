<?php
/**
 * Generic listing driven by $spec['columns'].
 * @var array $spec  @var array $rows  @var string $search
 */
$columns = $spec['columns'];
$base    = $spec['base'];
?>
<div class="filters">
  <?php if (!empty($spec['search'])): ?>
    <form class="search wide" method="get" action="<?= e(url($base)) ?>">
      <svg class="icon"><use href="#i-search"></use></svg>
      <input name="q" value="<?= e($search) ?>" placeholder="<?= e($spec['search_hint'] ?? 'Search') ?>">
      <?php foreach ($spec['filters'] ?? [] as $key => $filter): ?>
        <input type="hidden" name="<?= e($key) ?>" value="<?= e($_GET[$key] ?? '') ?>">
      <?php endforeach; ?>
    </form>
  <?php endif; ?>

  <?php foreach ($spec['filters'] ?? [] as $key => $filter): ?>
    <form method="get" action="<?= e(url($base)) ?>" style="display:contents">
      <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
      <select name="<?= e($key) ?>" onchange="this.form.submit()">
        <option value=""><?= e($filter['all'] ?? 'All') ?></option>
        <?php foreach ($filter['options'] as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= (string) ($_GET[$key] ?? '') === (string) $value ? ' selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endforeach; ?>

  <?php if ($search !== '' || array_filter($_GET ?? [], 'strlen')): ?>
    <a class="btn btn-ghost" href="<?= e(url($base)) ?>">Reset</a>
  <?php endif; ?>

  <?php foreach ($spec['actions'] ?? [] as $action): ?>
    <?php if (!empty($action['post'])): ?>
      <form method="post" action="<?= e(url($action['href'])) ?>" style="margin-left:auto">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="submit"><?= e($action['label']) ?></button>
      </form>
    <?php else: ?>
      <a class="btn btn-ghost" href="<?= e(url($action['href'])) ?>" style="margin-left:auto">
        <?= e($action['label']) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if (empty($spec['no_create'])): ?>
    <a class="btn btn-primary" href="<?= e(url($base . '/new')) ?>"
       <?= empty($spec['actions']) ? 'style="margin-left:auto"' : '' ?>>
      + Add <?= e(strtolower($spec['single'] ?? 'record')) ?>
    </a>
  <?php endif; ?>
</div>

<div class="box">
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <?php foreach ($columns as $column): ?>
            <th class="<?= e($column['class'] ?? '') ?>"><?= e($column['label']) ?></th>
          <?php endforeach; ?>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= count($columns) + 1 ?>" class="empty">
          <?= e($spec['empty'] ?? 'Nothing here yet.') ?>
        </td></tr>
      <?php else: foreach ($rows as $row): ?>
        <tr>
          <?php foreach ($columns as $column): ?>
            <td class="<?= e($column['class'] ?? '') ?>">
              <?php
              if (isset($column['render']) && is_callable($column['render'])) {
                  echo $column['render']($row);   // renderers emit their own escaped markup
              } else {
                  echo e($row[$column['key']] ?? '');
              }
              ?>
            </td>
          <?php endforeach; ?>
          <td class="ta-r" style="white-space:nowrap">
            <?php
            // A screen can add its own buttons per row; they emit escaped markup.
            if (isset($spec['row_actions']) && is_callable($spec['row_actions'])) {
                echo $spec['row_actions']($row);
            }
            ?>
            <?php if (!empty($spec['toggle'])): ?>
              <form method="post" action="<?= e(url($base . '/toggle')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">
                  <?= $row[$spec['toggle']] ? 'Disable' : 'Enable' ?>
                </button>
              </form>
            <?php endif; ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/edit/' . $row['id'])) ?>">Edit</a>
            <?php if (empty($spec['no_delete'])): ?>
              <form method="post" action="<?= e(url($base . '/delete')) ?>" style="display:inline"
                    data-confirm="Delete this <?= e(strtolower($spec['single'] ?? 'record')) ?>? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($rows): ?>
    <div class="pager"><span><?= qty_fmt(count($rows)) ?>
      <?= count($rows) === 1 ? strtolower($spec['single'] ?? 'record') : strtolower($spec['title']) ?></span></div>
  <?php endif; ?>
</div>
