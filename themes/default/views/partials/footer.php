<?php
/** Footer. The link columns come from the pages table. */
$siteName  = setting('site_name', 'SMM Panel');
$footPages = all('SELECT slug, title FROM pages WHERE is_active = 1 AND show_in_footer = 1
                  ORDER BY sort_order, id');
$topLinks  = all(
    'SELECT p.slug AS pslug, p.name AS pname, c.slug AS cslug, c.name AS cname
       FROM categories c
       JOIN platforms p ON p.id = c.platform_id
      WHERE c.is_active = 1 AND p.is_active = 1
        AND EXISTS (SELECT 1 FROM services s WHERE s.category_id = c.id AND s.is_active = 1)
   ORDER BY p.sort_order, c.sort_order
      LIMIT 5'
);
$methods = all('SELECT name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id');
?>
<footer id="contact-footer">
  <div class="wrap">
    <div class="fgrid">
      <div class="fabout">
        <a href="<?= e(url('')) ?>" class="logo">
          <span class="logo-mark"><svg class="icon"><use href="#i-rocket"></use></svg></span>
          <span><?= e($siteName) ?><small>SMM Panel</small></span>
        </a>
        <p><?= e(setting('site_tagline', 'Social media growth services. Safe, fast and no password ever required.')) ?></p>
        <?php if ($methods): ?>
          <div class="pays">
            <?php foreach ($methods as $method): ?>
              <span class="pay"><?= e($method['name']) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($topLinks): ?>
      <div>
        <h4>Services</h4>
        <ul>
          <?php foreach ($topLinks as $link): ?>
            <li><a href="<?= e(url($link['pslug'] . '/' . $link['cslug'])) ?>">
              <?= e($link['pname'] . ' ' . $link['cname']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <div>
        <h4>Company</h4>
        <ul>
          <li><a href="<?= e(url('faq')) ?>">FAQ</a></li>
          <li><a href="<?= e(url('contact')) ?>">Contact Us</a></li>
          <li><a href="<?= e(url('track')) ?>">Track Order</a></li>
        </ul>
      </div>

      <?php if ($footPages): ?>
      <div>
        <h4>Legal</h4>
        <ul>
          <?php foreach ($footPages as $page): ?>
            <li><a href="<?= e(url($page['slug'])) ?>"><?= e($page['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>

    <div class="fbottom">
      <span>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</span>
      <span>Made for creators &bull; Not affiliated with any platform</span>
    </div>
  </div>
</footer>
