<?php
/** One order: everything we know, plus the actions. */
$profit = (float) $order['price'] - (float) $order['cost'];
$margin = (float) $order['cost'] > 0 ? round(($profit / (float) $order['cost']) * 100) : null;

/** One action button, posted with the CSRF token. */
$button = static function (string $do, string $label, string $class = 'btn-ghost', ?string $confirm = null) use ($order) {
    $form = '<form method="post" action="' . e(url('admin/orders/action')) . '"';
    if ($confirm !== null) {
        $form .= ' data-confirm="' . e($confirm) . '"';
    }
    return $form . '>' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $order['id'] . '">'
        . '<input type="hidden" name="do" value="' . e($do) . '">'
        . '<button class="btn ' . e($class) . ' btn-block" type="submit">' . e($label) . '</button>'
        . '</form>';
};
?>
<a class="lnk back" href="<?= e(url('admin/orders')) ?>">&larr; Back to orders</a>

<div class="split2">
  <div>
    <div class="box">
      <div class="box-head">
        <b>Order <?= e($order['code']) ?></b>
        <span class="st st-<?= e($order['status']) ?>"><?= e(str_replace('_', ' ', $order['status'])) ?></span>
      </div>
      <div class="pad">
        <div class="drow"><span>Service</span><b><?= e($order['service_name']) ?></b></div>
        <div class="drow"><span>Platform</span><b><?= e($order['platform_name'] ?? '-') ?></b></div>
        <div class="drow"><span>Quantity</span><b><?= qty_fmt($order['quantity']) ?></b></div>
        <div class="drow"><span>Link</span><b>
          <a href="<?= e($order['link']) ?>" target="_blank" rel="noopener nofollow"><?= e($order['link']) ?></a>
        </b></div>
        <div class="drow"><span>WhatsApp</span><b><?= e($order['whatsapp'] ?: '-') ?></b></div>
        <div class="drow"><span>Charge / cost</span>
          <b><?= e(money($order['price'])) ?> / <?= e(money($order['cost'])) ?></b></div>
        <div class="drow"><span>Profit</span>
          <b class="<?= $profit >= 0 ? 'up' : 'warn' ?>"><?= e(money($profit)) ?><?php
            if ($margin !== null): ?> (<?= $margin >= 0 ? '+' : '' ?><?= (int) $margin ?>%)<?php endif; ?></b></div>
        <div class="drow"><span>Placed</span><b><?= e(when($order['created_at'])) ?></b></div>
        <div class="drow"><span>Customer IP</span><b class="mono"><?= e($order['ip'] ?: '-') ?></b></div>
      </div>
    </div>

    <div class="box">
      <div class="box-head"><b>Provider</b>
        <?php if ($order['provider_order_id'] !== ''): ?>
          <span class="st st-completed">sent</span>
        <?php elseif ($order['status'] === 'api_error'): ?>
          <span class="st st-api_error">failed</span>
        <?php else: ?>
          <span class="st st-pending">not sent</span>
        <?php endif; ?>
      </div>
      <div class="pad">
        <div class="drow"><span>Provider</span><b><?= e($order['provider_name'] ?? 'manual / none') ?></b></div>
        <div class="drow"><span>Provider order id</span>
          <b class="mono"><?= e($order['provider_order_id'] ?: '-') ?></b></div>
        <div class="drow"><span>Provider status</span><b><?= e($order['provider_status'] ?: '-') ?></b></div>
        <div class="drow"><span>Start count</span>
          <b><?= $order['start_count'] === null ? '-' : qty_fmt($order['start_count']) ?></b></div>
        <div class="drow"><span>Remains</span>
          <b><?= $order['remains'] === null ? '-' : qty_fmt($order['remains']) ?></b></div>
        <div class="drow"><span>Last synced</span><b><?= e(when($order['synced_at'])) ?></b></div>
        <?php if ($order['api_error'] !== ''): ?>
          <div class="alert alert-error" style="margin:14px 0 0"><?= e($order['api_error']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="box">
      <div class="box-head"><b>Payment</b>
        <span class="st st-<?= $order['paid_at'] ? 'completed' : 'pending' ?>">
          <?= $order['paid_at'] ? 'confirmed' : 'awaiting' ?></span>
      </div>
      <div class="pad">
        <div class="drow"><span>Method</span><b><?= e($order['payment_name'] ?? '-') ?></b></div>
        <div class="drow"><span>Transaction id</span><b class="mono"><?= e($order['trx_id'] ?: '-') ?></b></div>
        <div class="drow"><span>Amount sent</span>
          <b><?= $order['paid_amount'] === null ? '-' : e(money($order['paid_amount'])) ?></b></div>
        <div class="drow"><span>Confirmed at</span><b><?= e(when($order['paid_at'])) ?></b></div>

        <?php /* Whatever this method asked for. The labels come from the
                 definition stored at the time, so an order still reads the
                 same after the admin renames or removes a field. */ ?>
        <?php $answers = payfields_stored($order); ?>
        <?php if ($answers): ?>
          <dl class="pfa">
            <?php foreach ($answers as $key => $answer): ?>
              <div>
                <dt><?= e($answer['label'] ?? $key) ?></dt>
                <dd>
                  <?php if (($answer['type'] ?? '') === 'image'): ?>
                    <a class="pf-proof" target="_blank" rel="noopener"
                       href="<?= e(url('admin/orders/proof/' . (int) $order['id'] . '/' . $key)) ?>">
                      <img src="<?= e(url('admin/orders/proof/' . (int) $order['id'] . '/' . $key)) ?>"
                           alt="<?= e($answer['label'] ?? 'Payment screenshot') ?>" loading="lazy">
                    </a>
                  <?php else: ?>
                    <?= e((string) ($answer['value'] ?? '')) ?>
                  <?php endif; ?>
                </dd>
              </div>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="box">
      <div class="box-head"><b>Actions</b></div>
      <div class="pad acts">
        <?php if (in_array($order['status'], ['pending', 'api_error'], true)): ?>
          <?= $button('mark_paid', 'Mark as paid', 'btn-primary') ?>
        <?php endif; ?>

        <?php if (in_array($order['status'], ['paid', 'api_error'], true) && $order['provider_order_id'] === ''): ?>
          <?= $button('send', 'Send to provider', 'btn-primary') ?>
        <?php endif; ?>

        <?php if ($order['provider_order_id'] !== ''): ?>
          <?= $button('sync', 'Sync status now') ?>
        <?php endif; ?>

        <?php if ($order['provider_order_id'] !== '' && in_array($order['status'], ['completed', 'partial'], true)): ?>
          <?= $button('refill', 'Request refill') ?>
        <?php endif; ?>

        <?php if (!in_array($order['status'], ['completed', 'cancelled', 'refunded'], true)): ?>
          <?= $button('complete', 'Mark completed manually') ?>
          <?= $button('cancel', 'Cancel order', 'btn-ghost',
                      'Cancel this order? If it was sent to a provider we will try to cancel it there too.') ?>
        <?php endif; ?>

        <?php if ($order['status'] !== 'refunded'): ?>
          <?= $button('refund', 'Mark refunded', 'btn-danger',
                      'Mark this order refunded? You still have to send the money back yourself.') ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="box">
      <div class="box-head"><b>Activity</b></div>
      <div class="pad">
        <form method="post" action="<?= e(url('admin/orders/action')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
          <input type="hidden" name="do" value="note">
          <div class="field">
            <label for="f-note">Add an internal note</label>
            <input id="f-note" name="note" placeholder="Only admins see this">
          </div>
          <button class="btn btn-ghost btn-block" type="submit">Save note</button>
        </form>

        <ul class="log" style="margin-top:18px">
          <?php if (!$logs): ?>
            <li class="muted">Nothing logged yet.</li>
          <?php else: foreach ($logs as $entry): ?>
            <li><b><?= e(when($entry['created_at'], 'd M, H:i')) ?></b><?= e($entry['message']) ?></li>
          <?php endforeach; endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
