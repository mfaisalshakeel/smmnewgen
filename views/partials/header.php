<?php
/** Site header. The nav links are real pages, so they work without JS. */
$siteName = setting('site_name', 'SMM Panel');
$logo     = setting('logo_path', '');
$waNumber = preg_replace('/\D/', '', (string) setting('whatsapp_number', ''));
?>
<header>
  <div class="wrap nav">
    <a href="<?= e(url('')) ?>" class="logo">
      <?php if ($logo): ?>
        <img src="<?= e(url($logo)) ?>" alt="<?= e($siteName) ?>" style="height:38px;width:auto">
      <?php else: ?>
        <span class="logo-mark"><svg class="icon"><use href="#i-rocket"></use></svg></span>
        <span><?= e($siteName) ?><small>SMM Panel</small></span>
      <?php endif; ?>
    </a>

    <nav class="nav-links">
      <a href="<?= e(url('')) ?>">Home</a>
      <a href="<?= e(url('faq')) ?>">FAQ</a>
      <a href="<?= e(url('contact')) ?>">Contact</a>
    </nav>

    <div class="nav-right">
      <?php if ($waNumber !== ''): ?>
        <a href="https://wa.me/<?= e($waNumber) ?>" target="_blank" rel="noopener" class="wa-link">
          <svg class="icon" style="font-size:17px"><use href="#i-whatsapp"></use></svg> Need help?
        </a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= e(url('track')) ?>">
        <svg class="icon"><use href="#i-search"></use></svg> Track Order
      </a>
      <button class="burger" type="button" data-drawer aria-label="Menu">
        <svg class="icon" style="font-size:18px"><use href="#i-menu"></use></svg>
      </button>
    </div>
  </div>

  <div class="drawer" id="drawer">
    <div class="wrap" style="padding:0">
      <a href="<?= e(url('')) ?>">Home</a>
      <a href="<?= e(url('faq')) ?>">FAQ</a>
      <a href="<?= e(url('contact')) ?>">Contact</a>
      <?php if ($waNumber !== ''): ?>
        <a href="https://wa.me/<?= e($waNumber) ?>" target="_blank" rel="noopener">WhatsApp help</a>
      <?php endif; ?>
      <a class="btn btn-primary btn-block" href="<?= e(url('track')) ?>">Track Order</a>
    </div>
  </div>
</header>
