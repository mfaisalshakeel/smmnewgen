<?php
/** Site header. The nav links are real pages, so they work without JS. */
$siteName = setting('site_name', 'SMM Panel');
$logo     = setting('logo_path', '');

// One answer for both the bar and the drawer: the same link in two places
// must not be able to disagree about whether it is there.
$chat = chat_channels('header');
?>
<header>
  <div class="wrap nav">
    <a href="<?= e(url('')) ?>" class="logo">
      <?php if ($logo): ?>
        <img src="<?= e(url($logo)) ?>" alt="<?= e($siteName) ?>" style="height:38px;width:auto">
      <?php else: ?>
        <span class="logo-mark"><svg class="icon"><use href="#i-rocket"></use></svg></span>
        <span><?= e($siteName) ?></span>
      <?php endif; ?>
    </a>

    <nav class="nav-links">
      <a href="<?= e(url('')) ?>">Home</a>
      <a href="<?= e(url('faq')) ?>">FAQ</a>
      <a href="<?= e(url('contact')) ?>">Contact</a>
    </nav>

    <div class="nav-right">
      <?php foreach ($chat as $channel): ?>
        <a href="<?= e($channel['url']) ?>" class="chat-link"
           <?= $channel['key'] === 'email' ? '' : 'target="_blank" rel="noopener"' ?>
           title="<?= e($channel['title']) ?>">
          <svg class="icon" style="font-size:17px"><use href="#<?= e($channel['icon']) ?>"></use></svg>
          Need help?
        </a>
      <?php endforeach; ?>
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
      <?php /* No Track Order here: the header keeps it out of the burger on
               every width, so repeating it is the same link twice. */ ?>
      <?php foreach ($chat as $channel): ?>
        <?php /* Named here, not in the bar: a menu is a list of words, and
                 "Chat" three times over would be a list that says nothing. */ ?>
        <a href="<?= e($channel['url']) ?>"
           <?= $channel['key'] === 'email' ? '' : 'target="_blank" rel="noopener"' ?>>
          Help on <?= e($channel['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</header>
