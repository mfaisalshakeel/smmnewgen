<?php
/**
 * The hero.
 *
 * Both themes render this one file - the band, the phone and the chips are
 * the same shape whatever the colour, and the colour is mixed from --brand
 * by whichever stylesheet is loaded. A theme that wants a different hero
 * still only has to drop its own copy of this partial in.
 *
 * The band is the headline and nothing else - no badge, no promises, no
 * button. Every one of those was a thing to read before reaching the platform
 * bar, which is the choice the whole page hangs off and so the first thing
 * that should be tappable.
 *
 * The headline is one fixed sentence rather than the platform and category
 * the customer is looking at. Those are named by the tab that is lit, by the
 * heading over the cards and by the cards themselves; saying it a fourth
 * time in the largest type on the page only pushed the choice further down
 * it. Nothing here changes when the platform does, so the script has nothing
 * to rewrite in the band.
 *
 * @var array  $platform  @var ?array $category
 */
$noun = $category['name'] ?? 'Followers';
?>
<section class="hero">
  <span class="hero-orb" aria-hidden="true"></span>
  <span class="hero-ray" aria-hidden="true"></span>

  <div class="wrap hero-grid">
    <div class="hero-copy">
      <h1>Grow Your <b>Social Media</b> With Fast &amp; <b>Simple Services</b></h1>

      <?php /* Four things, said in two words each. The sentence this replaced
               said the same in thirty, which on a phone was three lines of
               reading between the headline and the platform bar. */ ?>
      <ul class="hero-points">
        <li><svg class="icon"><use href="#i-shield"></use></svg>
          <span><b>Safe</b><small>Simple process</small></span></li>
        <li><svg class="icon"><use href="#i-bolt"></use></svg>
          <span><b>Fast</b><small>Processing</small></span></li>
        <li><svg class="icon"><use href="#i-lock"></use></svg>
          <span><b>Secure</b><small>Checkout</small></span></li>
        <li><svg class="icon"><use href="#i-chat"></use></svg>
          <span><b>Support</b><small><?= e(support_channel_line()) ?></small></span></li>
      </ul>
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
