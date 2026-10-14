<?php
/**
 * Storefront.
 *
 * The one real difference from the default theme: the platform chooser is a
 * titled panel rather than a bare strip, because choosing the platform is the
 * decision the rest of the page hangs off. The hero is the shared partial.
 * Everything the script needs keeps its id - #platStrip, #catTabs,
 * #serviceCards, #heroTitle and its .grad span, #heroCta, #svcTitle.
 *
 * @var array  $platform    the platform being shown
 * @var ?array $category    the category tab, or null for the first one
 * @var array  $platforms   every active platform (the chooser)
 * @var array  $categories  active categories for this platform (the tabs)
 * @var array  $services    services in the current category
 * @var array  $faqs        FAQ entries for this platform
 */
$noun = $category['name'] ?? 'Followers';
?>
<?php partial('partials/hero', ['platform' => $platform, 'category' => $category]); ?>

<section class="psec" id="services">
  <div class="wrap">
    <?php /* No heading above it: the tiles are pictures of the platforms and
             say what they are, and two lines of text asking the question the
             row already answers is two lines the phone has to scroll past. */ ?>
    <div class="chooser">
      <div class="plat-scroll">
        <?php partial('partials/platforms', ['platforms' => $platforms, 'platform' => $platform]); ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Our Services</span>
      <h2 id="svcTitle"><?= e($platform['name'] . ' ' . $noun) ?></h2>
      <p>Pick a package, type the quantity you want and the price updates instantly.
         Delivery starts automatically after payment.</p>
    </div>

    <?php partial('partials/tabs', ['categories' => $categories, 'category' => $category, 'platform' => $platform]); ?>

    <?php partial('partials/cards', ['services' => $services, 'platform' => $platform, 'category' => $category]); ?>
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
    <div class="faq" id="faqList">
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
