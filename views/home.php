<?php
/**
 * Storefront.
 *
 * @var array  $platform    the platform being shown
 * @var ?array $category    the category tab, or null for the first one
 * @var array  $platforms   every active platform (the switcher)
 * @var array  $categories  active categories for this platform (the tabs)
 * @var array  $services    services in the current category
 * @var array  $faqs        FAQ entries for this platform
 */
$noun = $category['name'] ?? 'Followers';
?>
<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <div class="badge-trust">
        <span class="avatars"><span>A</span><span>M</span><span>S</span><span>+</span></span>
        <span class="stars">
          <?php for ($i = 0; $i < 5; $i++): ?><svg class="icon"><use href="#i-star"></use></svg><?php endfor; ?>
        </span>
        Trusted by 10,000+ users
      </div>

      <h1>Buy <span class="grad"><?= e($platform['name'] . ' ' . $noun) ?></span>
        With Safe &amp; Instant Delivery</h1>

      <p class="hero-sub">No password required &bull; Starts within <b>2&ndash;15 minutes</b> &bull;
        Secure payment &bull; High quality service &bull; <b>24/7 support</b></p>

      <div class="stats">
        <div class="stat"><div class="si"><svg class="icon"><use href="#i-shield"></use></svg></div>
          <b>100% Safe</b><small>&amp; Secure</small></div>
        <div class="stat"><div class="si"><svg class="icon"><use href="#i-bolt"></use></svg></div>
          <b>Instant</b><small>Delivery</small></div>
        <div class="stat"><div class="si"><svg class="icon"><use href="#i-users"></use></svg></div>
          <b>10,000+</b><small>Happy customers</small></div>
        <div class="stat"><div class="si"><svg class="icon"><use href="#i-chat"></use></svg></div>
          <b>24/7</b><small>Support</small></div>
      </div>

      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="#services">
          Buy <?= e($platform['name'] . ' ' . $noun) ?> &rarr;</a>
        <span class="hero-note">
          <svg class="icon" style="color:var(--ok)"><use href="#i-check"></use></svg>
          No login or password needed
        </span>
      </div>
    </div>

    <div class="pcard">
      <div class="float-pill">+10K <?= e($noun) ?></div>
      <div class="pcard-top">
        <div class="pavatar"><i>&#129489;&#8205;&#127908;</i></div>
        <div>
          <div class="phandle">@yourbrand <svg class="icon verified"><use href="#i-check"></use></svg></div>
          <div class="pbio">Creator &bull; Growing every day</div>
        </div>
      </div>
      <div class="pstats">
        <div><b>255</b><small>Posts</small></div>
        <div class="up"><b>12.5K</b><small><?= e($noun) ?></small></div>
        <div><b>318</b><small>Following</small></div>
      </div>
      <div class="pbtns">
        <a class="btn btn-primary" href="#services">Order now</a>
        <a class="btn btn-ghost" href="<?= e(url('track')) ?>">Track</a>
      </div>
    </div>
  </div>
</section>

<!-- Platform switcher: real links, so it works without JS and search engines
     can follow it. -->
<section class="psec" id="services">
  <div class="wrap">
    <div class="plat-scroll">
      <div class="plats">
        <?php foreach ($platforms as $p): ?>
          <a class="plat<?= $p['id'] === $platform['id'] ? ' active' : '' ?>"
             style="--pc:<?= e($p['color'] ?: '#6c4df6') ?>"
             href="<?= e(url($p['slug'])) ?>">
            <svg class="icon"><use href="#<?= e($p['icon'] ?: 'i-rocket') ?>"></use></svg>
            <?= e($p['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:28px">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Our Services</span>
      <h2><?= e($platform['name'] . ' ' . $noun) ?></h2>
      <p>Pick a package, type the quantity you want and the price updates instantly.
         Delivery starts automatically after payment.</p>
    </div>

    <?php if ($categories): ?>
      <div class="tabs">
        <?php foreach ($categories as $c): ?>
          <a class="tab<?= ($category && $c['id'] === $category['id']) ? ' active' : '' ?>"
             href="<?= e(url($platform['slug'] . '/' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="cards">
      <?php if (!$services): ?>
        <div class="empty-state">
          <b>Nothing here yet</b>
          We are adding <?= e(strtolower($platform['name'] . ' ' . $noun)) ?> services shortly.
          Try another tab above.
        </div>
      <?php else: foreach ($services as $service): ?>
        <?php partial('partials/service-card', ['service' => $service, 'platform' => $platform]); ?>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--bg-soft);border-top:1px solid var(--line);
         border-bottom:1px solid var(--line)">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">How it works</span>
      <h2>Three steps, done in minutes</h2>
    </div>
    <div class="steps">
      <div class="step"><div class="step-n">1</div><h3>Choose your package</h3>
        <p>Pick the service, type the quantity you need and see the exact price before you pay.</p></div>
      <div class="step"><div class="step-n">2</div><h3>Share link &amp; pay</h3>
        <p>Give us your public profile or post link and your WhatsApp number, then pay with any
           method we list.</p></div>
      <div class="step"><div class="step-n">3</div><h3>Watch it grow</h3>
        <p>Delivery starts within 2&ndash;15 minutes. Track your order any time with the code we
           send you.</p></div>
    </div>
  </div>
</section>

<?php if ($faqs): ?>
<section class="section" id="faq">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">FAQ</span>
      <h2>Questions people ask us</h2>
    </div>
    <div class="faq">
      <?php foreach ($faqs as $i => $faq): ?>
        <div class="fitem<?= $i === 0 ? ' open' : '' ?>">
          <button class="fq" type="button" data-faq><?= e($faq['question']) ?><i>+</i></button>
          <div class="fa"<?= $i === 0 ? ' style="max-height:400px"' : '' ?>>
            <p><?= e($faq['answer']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php $wa = preg_replace('/\D/', '', (string) setting('whatsapp_number', '')); ?>
<?php if ($wa !== ''): ?>
<section class="wrap">
  <div class="band">
    <h2>Not sure which package to pick?</h2>
    <p>Message us on WhatsApp and we will recommend the right service for your account &mdash;
       usually within a couple of minutes.</p>
    <a class="btn btn-ghost btn-lg" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">
      <svg class="icon"><use href="#i-whatsapp"></use></svg> Chat on WhatsApp
    </a>
  </div>
</section>
<?php endif; ?>
