<?php
/** The feed. @var array $rows  @var int $unread */
?>
<div class="filters">
  <a class="btn btn-ghost" href="<?= e(url('admin/notifications/rules')) ?>">
    <svg class="icon"><use href="#i-gear"></use></svg> What reaches me
  </a>
  <?php if ($unread): ?>
    <form method="post" action="<?= e(url('admin/notifications/read')) ?>" style="margin-left:auto">
      <?= csrf_field() ?>
      <button class="btn btn-ghost" type="submit">Mark all read</button>
    </form>
  <?php endif; ?>
  <form method="post" action="<?= e(url('admin/notifications/clear')) ?>"
        <?= $unread ? '' : 'style="margin-left:auto"' ?>
        data-confirm="Clear every notification you have already read?">
    <?= csrf_field() ?>
    <button class="btn btn-ghost" type="submit">Clear read</button>
  </form>
</div>

<div class="box">
  <?php if (!$rows): ?>
    <div class="empty-state">
      <b>Nothing yet.</b>
      <p class="muted">Orders, payments and provider trouble will show up here.</p>
    </div>
  <?php else: ?>
    <ul class="nlist">
      <?php foreach ($rows as $row): ?>
        <li class="nitem<?= $row['is_read'] ? '' : ' is-unread' ?>">
          <span class="ndot" aria-hidden="true"></span>
          <div class="nbody">
            <b><?= e($row['title']) ?></b>
            <?php if ($row['body'] !== ''): ?>
              <small class="sub"><?= e($row['body']) ?></small>
            <?php endif; ?>
            <small class="muted"><?= e(when($row['created_at'], 'd M, H:i')) ?></small>
          </div>
          <div class="nacts">
            <?php if ($row['link'] !== ''): ?>
              <a class="btn btn-ghost btn-sm" href="<?= e(url($row['link'])) ?>">Open</a>
            <?php endif; ?>
            <?php if (!$row['is_read']): ?>
              <form method="post" action="<?= e(url('admin/notifications/read')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button class="iact iact-on" type="submit" title="Mark read" aria-label="Mark read">
                  <svg class="icon"><use href="#i-check"></use></svg>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
