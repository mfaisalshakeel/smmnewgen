<?php /** Contact messages. */ ?>
<div class="filters">
  <form method="get" action="<?= e(url('admin/messages')) ?>" style="display:contents">
    <select name="state" onchange="this.form.submit()">
      <option value="">All messages</option>
      <option value="unread"<?= $filter === 'unread' ? ' selected' : '' ?>>Unread only</option>
      <option value="read"<?= $filter === 'read' ? ' selected' : '' ?>>Read only</option>
    </select>
  </form>
  <?php if ($unread > 0): ?>
    <form method="post" action="<?= e(url('admin/messages/read-all')) ?>" style="margin-left:auto">
      <?= csrf_field() ?>
      <button class="btn btn-ghost" type="submit">Mark all read (<?= qty_fmt($unread) ?>)</button>
    </form>
  <?php endif; ?>
</div>

<div class="box">
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr><th>From</th><th>Message</th><th class="hide-sm">Received</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$messages): ?>
        <tr><td colspan="4" class="empty">No messages yet.</td></tr>
      <?php else: foreach ($messages as $message): ?>
        <tr<?= $message['is_read'] ? ' class="dim"' : '' ?>>
          <td>
            <b><?= e($message['name']) ?></b>
            <small class="sub">
              <?= e($message['email'] ?: $message['whatsapp'] ?: 'no contact given') ?>
            </small>
          </td>
          <td style="white-space:pre-wrap;max-width:460px"><?= e($message['body']) ?></td>
          <td class="hide-sm"><?= e(when($message['created_at'])) ?></td>
          <td class="ta-r" style="white-space:nowrap">
            <form method="post" action="<?= e(url('admin/messages/read')) ?>" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit">
                <?= $message['is_read'] ? 'Unread' : 'Read' ?></button>
            </form>
            <form method="post" action="<?= e(url('admin/messages/delete')) ?>" style="display:inline"
                  data-confirm="Delete this message?">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
