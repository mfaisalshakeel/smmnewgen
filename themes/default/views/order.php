<?php
/**
 * Order page: summary, payment details, and the live status timeline.
 * @var array $order  @var array $methods
 */
$statusLabels = [
    'pending'    => ['Awaiting payment', 'sb-pending'],
    'paid'       => ['Payment received', 'sb-paid'],
    'processing' => ['Processing',       'sb-processing'],
    'partial'    => ['Partly delivered', 'sb-processing'],
    'completed'  => ['Completed',        'sb-completed'],
    'cancelled'  => ['Cancelled',        'sb-pending'],
    'refunded'   => ['Refunded',         'sb-pending'],
    'api_error'  => ['Needs attention',  'sb-pending'],
];
[$statusLabel, $statusClass] = $statusLabels[$order['status']] ?? ['Processing', 'sb-processing'];

$awaitingPayment = in_array($order['status'], ['pending', 'api_error'], true);
$delivered = $order['remains'] !== null
    ? max(0, (int) $order['quantity'] - (int) $order['remains'])
    : ($order['status'] === 'completed' ? (int) $order['quantity'] : 0);
$progress = (int) $order['quantity'] > 0
    ? min(100, (int) round($delivered / (int) $order['quantity'] * 100))
    : 0;
if ($progress === 0 && !$awaitingPayment) { $progress = 12; }

$step = match ($order['status']) {
    'pending', 'api_error'   => 1,
    'paid'                   => 2,
    'processing', 'partial'  => 3,
    'completed'              => 4,
    default                  => 1,
};

// Written out once and handed to the channels, which prefill it where they
// can. Telegram's links cannot carry a message, which is why the order code
// is also on the screen with a copy button beside it.
$chatMessage = "Hi, order {$order['code']}\n"
    . qty_fmt($order['quantity']) . ' x ' . order_label($order) . "\n"
    . 'Amount: ' . money($order['price'])
    . ($order['trx_id'] !== '' ? "\nTRX: {$order['trx_id']}" : '');

$chat = chat_channels('order', $chatMessage);
?>
<main class="wrap opage">
  <?php if (($order['status'] ?? '') === 'pending'): ?>
    <div class="oplaced">
      <span class="om-tick"><svg class="icon"><use href="#i-check"></use></svg></span>
      <div>
        <b>Order placed</b>
        <small>Keep the code below &mdash; it is how you track this order.</small>
      </div>
    </div>
  <?php endif; ?>

  <div class="obar">
    <div class="ocode">
      <small>Order code</small>
      <span class="ocode-row">
        <?= e($order['code']) ?>
        <button class="ocode-copy" type="button" data-copy="<?= e($order['code']) ?>"
                aria-label="Copy the order code">Copy</button>
      </span>
    </div>
    <span class="sbadge <?= e($statusClass) ?>"><span class="d"></span> <?= e($statusLabel) ?></span>
    <a href="<?= e(url('')) ?>" class="btn btn-ghost" style="margin-left:auto">&larr; Back to services</a>
  </div>

  <?php /* While it is waiting to be paid, the pay panel is the whole
           point of the page and goes first on a narrow screen - see
           .ogrid-pay. Once it is paid, the delivery status is. */ ?>
  <div class="ogrid<?= $awaitingPayment ? ' ogrid-pay' : '' ?>">
    <!-- ======================= left ======================= -->
    <div>
      <div class="panel">
        <div class="ptitle"><svg class="icon"><use href="#i-card"></use></svg> Order summary</div>
        <div class="psubtitle">Placed <?= e(when($order['created_at'])) ?>
          &bull; we never asked for your password</div>

        <?php if ($order['platform_name']): ?>
          <div class="orow"><span>Platform</span><b><?= e($order['platform_name']) ?></b></div>
        <?php endif; ?>
        <div class="orow"><span>Service</span><b><?= e(order_label($order)) ?></b></div>
        <div class="orow"><span>Quantity</span><b><?= qty_fmt($order['quantity']) ?></b></div>
        <div class="orow"><span>Link</span><b><?= e($order['link']) ?></b></div>
        <?php if (($order['whatsapp'] ?? '') !== ''): ?>
          <div class="orow"><span>WhatsApp</span><b><?= e($order['whatsapp']) ?></b></div>
        <?php endif; ?>
        <div class="ototal"><span>Total to pay</span><span class="big"><?= e(money($order['price'])) ?></span></div>
      </div>

      <div class="panel">
        <div class="ptitle"><svg class="icon"><use href="#i-bolt"></use></svg> Delivery status</div>
        <div class="psubtitle">Updates automatically &mdash; reload this page any time</div>

        <ul class="tl">
          <li class="done">
            <span class="dot"><svg class="icon"><use href="#i-check"></use></svg></span>
            <div><b>Order placed</b><small><?= e(when($order['created_at'], 'd M Y, H:i')) ?></small></div>
          </li>
          <li class="<?= $step > 2 ? 'done' : ($step === 2 ? 'now' : '') ?>">
            <span class="dot"><?= $step > 2 ? '<svg class="icon"><use href="#i-check"></use></svg>' : '2' ?></span>
            <div><b>Payment received</b><small><?php
              if ($order['paid_at']) {
                  echo e(when($order['paid_at'], 'd M Y, H:i'));
              } elseif ($order['trx_id'] !== '') {
                  echo 'Submitted &mdash; we are confirming it';
              } else {
                  echo 'Waiting for your payment';
              } ?></small></div>
          </li>
          <li class="<?= $step > 3 ? 'done' : ($step === 3 ? 'now' : '') ?>">
            <span class="dot"><?= $step > 3 ? '<svg class="icon"><use href="#i-check"></use></svg>' : '3' ?></span>
            <div><b>Processing</b><small>Delivery in progress</small></div>
          </li>
          <li class="<?= $step >= 4 ? 'done' : '' ?>">
            <span class="dot"><?= $step >= 4 ? '<svg class="icon"><use href="#i-check"></use></svg>' : '4' ?></span>
            <div><b>Completed</b><small>Everything delivered</small></div>
          </li>
        </ul>

        <div class="progress"><i style="width:<?= $progress ?>%"></i></div>
        <div class="counts">
          <div class="count"><b><?= $order['start_count'] === null ? '&mdash;' : qty_fmt($order['start_count']) ?></b>
            <small>Start count</small></div>
          <div class="count"><b><?= qty_fmt($delivered) ?></b><small>Delivered</small></div>
          <div class="count"><b><?= $order['remains'] === null
                ? qty_fmt($order['quantity']) : qty_fmt($order['remains']) ?></b><small>Remains</small></div>
        </div>
      </div>
    </div>

    <!-- ======================= right ======================= -->
    <div>
      <?php if ($awaitingPayment): ?>
        <?php partial('partials/pay', ['order' => $order, 'methods' => $methods,
            'gateways' => $gateways, 'chat' => $chat, 'payErrors' => $payErrors]); ?>
      <?php else: ?>
        <div class="panel">
          <div class="ptitle"><svg class="icon"><use href="#i-check"></use></svg> Payment confirmed</div>
          <div class="psubtitle">Nothing more to do &mdash; your order is on its way.</div>
          <div class="orow"><span>Method</span><b><?= $order['payment_name'] ? e($order['payment_name']) : '&mdash;' ?></b></div>
          <?php if ($order['trx_id'] !== ''): ?>
            <div class="orow"><span>Transaction id</span><b><?= e($order['trx_id']) ?></b></div>
          <?php endif; ?>
          <?php if ($order['paid_at']): ?>
            <div class="orow"><span>Confirmed</span><b><?= e(when($order['paid_at'])) ?></b></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="panel">
        <div class="ptitle"><svg class="icon"><use href="#i-search"></use></svg> Keep this code</div>
        <div class="psubtitle">Check on this order any time at
          <a href="<?= e(url('track')) ?>" style="color:var(--brand);font-weight:700">
            <?= e(str_replace('https://', '', url('track'))) ?></a>
        </div>
        <div class="acct">
          <div><small>Your order code</small><b><?= e($order['code']) ?></b></div>
          <button class="copy" type="button" data-copy="<?= e($order['code']) ?>">Copy</button>
        </div>
        <?php if ($chat): ?>
          <p class="help">Something wrong?
            <?php foreach ($chat as $i => $channel): ?><?= $i ? ' &bull; ' : '' ?><a
               href="<?= e($channel['url']) ?>"
               <?= $channel['key'] === 'email' ? '' : 'target="_blank" rel="noopener"' ?>
               >Message us on <?= e($channel['name']) ?></a><?php endforeach; ?>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

