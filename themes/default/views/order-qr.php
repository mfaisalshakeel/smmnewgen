<?php
/**
 * Paying by QR: the code to scan, the amount, and a page that notices when
 * the money arrives.
 *
 * The QR and the amount are worked out by the gateway and handed over here
 * already drawn - this file only places them.
 *
 * @var array  $order  @var array $method  @var array $qr  @var string $svg
 */
$chat = chat_channels('order');
?>
<main class="wrap opage">
  <div class="qrpay">
    <div class="panel">
      <div class="ptitle"><svg class="icon"><use href="#i-shield"></use></svg>
        Pay with <?= e($method['name']) ?></div>
      <div class="psubtitle">Order <?= e($order['code']) ?> &mdash;
        <?= e(order_label($order)) ?>, <?= qty_fmt($order['quantity']) ?></div>

      <div class="qrwait" id="qrWait">
        <span class="d"></span> <span id="qrWaitText">Waiting for your payment</span>
      </div>

      <div class="qrbox">
        <?= $svg /* built by qr_svg(): our own markup, nothing from the request */ ?>
      </div>

      <div class="qramt">
        <small>Amount to pay</small>
        <b><?= e($qr['amount'] !== '' ? $qr['amount'] : money((float) $order['price'])) ?></b>
      </div>

      <?php if ($qr['caption'] !== ''): ?>
        <p class="qrcap"><?= e($qr['caption']) ?></p>
      <?php endif; ?>

      <?php /* A phone cannot scan the screen it is showing, so on a phone the
               same intent is a link straight into the UPI app. */ ?>
      <a class="btn btn-primary btn-block" href="<?= e($qr['text']) ?>">
        <svg class="icon"><use href="#i-bolt"></use></svg> Pay in your UPI app
      </a>

      <?php /* The script checks on its own; this is the button for a browser
               that never ran it, and for anyone who would rather press it. */ ?>
      <form method="post" action="<?= e(url('order/' . $order['code'] . '/check')) ?>"
            class="stack" id="qrCheck">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-block" type="submit">I have paid &mdash; check now</button>
      </form>

      <div class="note">
        <svg class="icon"><use href="#i-clock"></use></svg>
        <span>Keep this page open. The order confirms itself as soon as
              <?= e($method['name']) ?> reports the payment, and delivery starts
              right after.</span>
      </div>
    </div>

    <div class="panel">
      <div class="ptitle"><svg class="icon"><use href="#i-search"></use></svg> Keep this code</div>
      <div class="psubtitle">It is how you check on this order later.</div>
      <div class="acct">
        <div><small>Your order code</small><b><?= e($order['code']) ?></b></div>
        <button class="copy" type="button" data-copy="<?= e($order['code']) ?>">Copy</button>
      </div>
      <p class="help">
        <a href="<?= e(url('order/' . $order['code'])) ?>">Pay another way instead</a>
        <?php foreach ($chat as $channel): ?>
          &bull; <a href="<?= e($channel['url']) ?>"
             <?= $channel['key'] === 'email' ? '' : 'target="_blank" rel="noopener"' ?>
             >Message us on <?= e($channel['name']) ?></a>
        <?php endforeach; ?>
      </p>
    </div>
  </div>
</main>

<script>
/* Ask the server whether the money has landed, one request at a time.
   setTimeout rather than setInterval: a slow answer must not stack a queue of
   requests behind it, each of which costs an outbound call to the gateway.
   Without this script the page is the button above, which does the same thing
   in one round trip. */
(function () {
  var form = document.getElementById('qrCheck');
  var wait = document.getElementById('qrWait');
  var text = document.getElementById('qrWaitText');
  if (!form || !wait || !window.fetch) { return; }

  var EVERY = 5000;
  var GIVE_UP_AFTER = 20 * 60 * 1000;   /* a QR nobody scanned in 20 minutes */
  var started = Date.now();

  function stop(message, className) {
    wait.className = 'qrwait ' + className;
    text.textContent = message;
  }

  function ask() {
    if (Date.now() - started > GIVE_UP_AFTER) {
      stop('Still waiting. Press the button below once you have paid.', 'qrwait-idle');
      return;
    }

    var body = new FormData(form);

    fetch(form.action, {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    }).then(function (response) { return response.json(); })
      .then(function (out) {
        if (out && out.state === 'paid') {
          stop('Payment received. Taking you to your order…', 'qrwait-ok');
          window.location.href = out.redirect || '<?= e(url('order/' . $order['code'])) ?>';
          return;
        }
        if (out && out.state === 'failed') {
          stop(out.message || 'That payment did not go through. You can scan again.',
               'qrwait-bad');
          return;
        }
        if (out && out.message) { text.textContent = out.message; }
        setTimeout(ask, EVERY);
      })
      .catch(function () {
        /* A dropped request says nothing about the payment: try again. */
        setTimeout(ask, EVERY);
      });
  }

  setTimeout(ask, EVERY);
})();
</script>
