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

$wa = preg_replace('/\D/', '', (string) setting('whatsapp_number', ''));
$waMessage = rawurlencode(
    "Hi, order {$order['code']}\n"
    . qty_fmt($order['quantity']) . " x {$order['service_name']}\n"
    . 'Amount: ' . money($order['price'])
    . ($order['trx_id'] !== '' ? "\nTRX: {$order['trx_id']}" : '')
);
?>
<main class="wrap opage">
  <div class="obar">
    <div class="ocode"><small>Order code</small><?= e($order['code']) ?></div>
    <span class="sbadge <?= e($statusClass) ?>"><span class="d"></span> <?= e($statusLabel) ?></span>
    <a href="<?= e(url('')) ?>" class="btn btn-ghost" style="margin-left:auto">&larr; Back to services</a>
  </div>

  <div class="ogrid">
    <!-- ======================= left ======================= -->
    <div>
      <div class="panel">
        <div class="ptitle"><svg class="icon"><use href="#i-card"></use></svg> Order summary</div>
        <div class="psubtitle">Placed <?= e(when($order['created_at'])) ?>
          &bull; we never asked for your password</div>

        <?php if ($order['platform_name']): ?>
          <div class="orow"><span>Platform</span><b><?= e($order['platform_name']) ?></b></div>
        <?php endif; ?>
        <div class="orow"><span>Service</span><b><?= e($order['service_name']) ?></b></div>
        <div class="orow"><span>Quantity</span><b><?= qty_fmt($order['quantity']) ?></b></div>
        <div class="orow"><span>Link</span><b><?= e($order['link']) ?></b></div>
        <div class="orow"><span>WhatsApp</span><b><?= e($order['whatsapp']) ?></b></div>
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
        <div class="panel">
          <div class="ptitle"><svg class="icon"><use href="#i-shield"></use></svg>
            Pay <?= e(money($order['price'])) ?></div>
          <div class="psubtitle">Send the exact amount, then tell us the transaction id below.</div>

          <?php if (!$methods): ?>
            <div class="note">
              <svg class="icon"><use href="#i-chat"></use></svg>
              <span>No payment methods are set up yet.
                <?php if ($wa !== ''): ?>Message us on WhatsApp and we will send you the details.<?php endif; ?>
              </span>
            </div>
          <?php else: ?>
            <form method="post" action="<?= e(url('order/' . $order['code'] . '/pay')) ?>">
              <?= csrf_field() ?>

              <div class="pm">
                <?php foreach ($methods as $i => $method): ?>
                  <div class="pmethod<?= $i === 0 ? ' on' : '' ?>" data-pm>
                    <label class="pmhead">
                      <input type="radio" name="payment_method_id" value="<?= (int) $method['id'] ?>"
                             <?= $i === 0 ? 'checked' : '' ?> hidden data-pm-radio>
                      <span class="pmlogo" style="background:var(--brand)">
                        <?= e($method['short_name'] !== ''
                             ? $method['short_name']
                             : mb_strtoupper(mb_substr($method['name'], 0, 2))) ?>
                      </span>
                      <span>
                        <b><?= e($method['name']) ?></b>
                        <small><?= e($method['account_title']) ?></small>
                      </span>
                      <span class="pmradio"></span>
                    </label>

                    <div class="pmbody">
                      <div class="acct">
                        <div><small>Account number</small><b><?= e($method['account_number']) ?></b></div>
                        <button class="copy" type="button"
                                data-copy="<?= e($method['account_number']) ?>">Copy</button>
                      </div>
                      <?php if ($method['account_title'] !== ''): ?>
                        <div class="acct"><div><small>Account title</small>
                          <b><?= e($method['account_title']) ?></b></div></div>
                      <?php endif; ?>
                      <?php if ($method['extra_value'] !== ''): ?>
                        <div class="acct"><div><small><?= e($method['extra_label'] ?: 'Details') ?></small>
                          <b><?= e($method['extra_value']) ?></b></div></div>
                      <?php endif; ?>
                      <?php if (trim((string) $method['instructions']) !== ''): ?>
                        <div class="note"><svg class="icon"><use href="#i-chat"></use></svg>
                          <span><?= e($method['instructions']) ?></span></div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="note">
                <svg class="icon"><use href="#i-clock"></use></svg>
                <span>Payments are confirmed by a person, usually within a few minutes.
                      Delivery starts right after.</span>
              </div>

              <div class="stack">
                <div class="field" style="margin-bottom:0">
                  <label for="trx">Transaction id<?= setting('require_trx_id', '1') === '1' ? '' : ' (optional)' ?></label>
                  <input id="trx" name="trx_id" value="<?= e($order['trx_id']) ?>"
                         placeholder="e.g. 102938475601"
                         <?= setting('require_trx_id', '1') === '1' ? 'required' : '' ?>>
                  <small>You get this in the SMS or app receipt after sending the payment.</small>
                </div>
                <div class="field" style="margin-bottom:0">
                  <label for="amt">Amount you sent</label>
                  <input id="amt" name="paid_amount" inputmode="decimal"
                         value="<?= e($order['paid_amount'] ?? $order['price']) ?>">
                </div>
                <button class="btn btn-primary btn-block btn-lg" type="submit">Submit payment</button>
                <?php if ($wa !== ''): ?>
                  <a class="btn btn-wa btn-block" target="_blank" rel="noopener"
                     href="https://wa.me/<?= e($wa) ?>?text=<?= $waMessage ?>">
                    <svg class="icon"><use href="#i-whatsapp"></use></svg> Send receipt on WhatsApp
                  </a>
                <?php endif; ?>
              </div>
            </form>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <div class="panel">
          <div class="ptitle"><svg class="icon"><use href="#i-check"></use></svg> Payment confirmed</div>
          <div class="psubtitle">Nothing more to do &mdash; your order is on its way.</div>
          <div class="orow"><span>Method</span><b><?= e($order['payment_name'] ?? '&mdash;') ?></b></div>
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
        <?php if ($wa !== ''): ?>
          <p class="help">Something wrong?
            <a href="https://wa.me/<?= e($wa) ?>?text=<?= $waMessage ?>" target="_blank" rel="noopener">
              Message us on WhatsApp</a>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
