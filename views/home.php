<?php /** Placeholder storefront - Phase 4 replaces this with the real design. */ ?>
<div class="wrap" style="padding:10vh 0;text-align:center">
  <h1 style="font-size:clamp(26px,5vw,42px);margin-bottom:14px"><?= e($title) ?></h1>
  <p style="color:#6b6b80;margin-bottom:26px">
    The admin panel is ready. The storefront is built in the next phase.
  </p>
  <p style="color:#9a9ab0;font-size:14px">
    <?= qty_fmt($counts['platforms']) ?> platforms &bull;
    <?= qty_fmt($counts['services']) ?> active services &bull;
    <?= qty_fmt($counts['orders']) ?> orders
  </p>
  <p style="margin-top:28px"><a class="btn btn-primary" href="<?= e(url('admin')) ?>">Go to admin</a></p>
</div>
