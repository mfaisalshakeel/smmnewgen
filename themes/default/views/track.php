<?php
/** @var string $code  @var ?array $order  @var ?string $error */
$delivered = $order && $order['remains'] !== null
    ? max(0, (int) $order['quantity'] - (int) $order['remains'])
    : ($order && $order['status'] === 'completed' ? (int) $order['quantity'] : 0);
?>
<section class="page-head">
  <div class="wrap">
    <h1>Track your order</h1>
    <p>Enter the order code we sent you on WhatsApp.</p>
  </div>
</section>

<section class="section" style="padding-top:24px">
  <div class="wrap track-wrap">
    <div class="track-card">
      <form class="track-form" method="get" action="<?= e(url('track')) ?>">
        <input name="code" value="<?= e($code) ?>" placeholder="GK-8F42KD" required
               autocomplete="off" aria-label="Order code">
        <button class="btn btn-primary btn-lg" type="submit">
          <svg class="icon"><use href="#i-search"></use></svg> Track
        </button>
      </form>

      <?php if ($error): ?>
        <div class="alert alert-error" style="margin:20px 0 0"><?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($order): ?>
        <div class="summary" style="margin:22px 0 0">
          <div class="si"><svg class="icon"><use href="#i-bolt"></use></svg></div>
          <div>
            <b><?= qty_fmt($order['quantity']) ?> &times; <?= e($order['service_name']) ?></b>
            <small>Status: <?= e(str_replace('_', ' ', $order['status'])) ?>
              &bull; <?= qty_fmt($delivered) ?> / <?= qty_fmt($order['quantity']) ?> delivered</small>
          </div>
        </div>

        <div class="progress" style="margin-top:16px">
          <i style="width:<?= (int) $order['quantity'] > 0
                ? min(100, (int) round($delivered / (int) $order['quantity'] * 100)) : 0 ?>%"></i>
        </div>

        <div class="counts">
          <div class="count"><b><?= $order['start_count'] === null
                ? '&mdash;' : qty_fmt($order['start_count']) ?></b><small>Start count</small></div>
          <div class="count"><b><?= qty_fmt($delivered) ?></b><small>Delivered</small></div>
          <div class="count"><b><?= $order['remains'] === null
                ? qty_fmt($order['quantity']) : qty_fmt($order['remains']) ?></b><small>Remains</small></div>
        </div>

        <p style="margin-top:18px">
          <a class="btn btn-ghost btn-block" href="<?= e(url('order/' . $order['code'])) ?>">
            Open the full order page
          </a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>
