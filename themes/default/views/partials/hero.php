<?php
/**
 * The hero.
 *
 * Both themes render this one file - the band, the phone and the chips are
 * the same shape whatever the colour, and the colour is mixed from --brand
 * by whichever stylesheet is loaded. A theme that wants a different hero
 * still only has to drop its own copy of this partial in.
 *
 * The script swaps the platform and category without reloading, and what it
 * looks for here is #heroTitle with a .grad span inside it, which it rewrites
 * with textContent. There is no call to action and no row of promises: the
 * badge, the headline and one line under it, and then the platform bar is the
 * first thing to tap.
 *
 * @var array  $platform  @var ?array $category
 */
$noun  = $category['name'] ?? 'Followers';
$label = $platform['name'] . ' ' . $noun;
?>
<section class="hero">
  <span class="hero-orb" aria-hidden="true"></span>
  <span class="hero-ray" aria-hidden="true"></span>

  <div class="wrap hero-grid">
    <div class="hero-copy">
      <span class="badge-trust">
        <svg class="icon"><use href="#i-bolt"></use></svg> Trusted by 10,000+ users
      </span>

      <h1 id="heroTitle">Buy <span class="grad"><?= e($label) ?></span>
        <span class="h1-rest">With Safe &amp; <b>Instant Delivery</b></span></h1>

      <p class="hero-sub">No password required &bull; Starts within 2&ndash;15 minutes &bull;
        Secure payment &bull; High quality service &bull; 24/7 support</p>
    </div>

    <!-- Decoration. Every word in it is repeated in the copy beside it, so
         there is nothing here for a screen reader to lose. -->
    <div class="hero-art" aria-hidden="true">
      <div class="phone">
        <div class="ph-head">
          <span class="ph-av"></span>
          <span class="ph-say">Grow your account</span>
        </div>
        <div class="ph-nums"><span>Posts</span><span><?= e($noun) ?></span><span>Following</span></div>
        <div class="ph-follow">Follow</div>
        <div class="ph-bars"><i></i><i></i><i></i></div>
        <div class="ph-grid"><i></i><i></i><i></i><i></i><i></i><i></i></div>
      </div>

      <span class="chip chip-1"><svg class="icon"><use href="#i-users"></use></svg>
        <span><b>+10K</b><small><?= e($noun) ?></small></span></span>
      <span class="chip chip-2"><svg class="icon"><use href="#i-bolt"></use></svg>
        <span><b>Fast</b><small>Delivery</small></span></span>
      <span class="chip chip-3"><svg class="icon"><use href="#i-shield"></use></svg>
        <span><b>100%</b><small>Secure</small></span></span>
    </div>
  </div>

  <!-- The light page rising into the band. A shape, not an image, so it
       re-colours with the theme and costs no request. -->
  <span class="hero-edge" aria-hidden="true"></span>
</section>
