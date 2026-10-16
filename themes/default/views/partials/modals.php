<?php /** Order modal. The track form is a real page, so it needs no modal. */ ?>
<div class="overlay" id="orderModal" data-overlay>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle"
       tabindex="-1">
    <div class="mhead">
      <div>
        <h3 id="orderModalTitle">Complete Your Order</h3>
        <p>Takes less than a minute</p>
      </div>
      <button class="mclose" type="button" data-close aria-label="Close">&times;</button>
    </div>

    <form class="mbody" id="orderForm" method="post" action="<?= e(url('order')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="service_id" id="omService">
      <input type="hidden" name="quantity" id="omQuantity">
      <input type="hidden" name="package_id" id="omPackage">

      <!-- Bots fill this in; people never see it. -->
      <div class="hp" aria-hidden="true">
        <label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <?php /* One line: how many, of what, and what it costs. The icon said
               nothing the words did not, and the delivery note repeated what
               the card the customer just pressed already told them - both
               were height between them and the fields they came here to
               fill in. */ ?>
      <div class="summary">
        <b id="omTitle">&nbsp;</b>
        <span class="sep" aria-hidden="true">&bull;</span>
        <span class="amt" id="omPrice">&nbsp;</span>
      </div>

      <?php /* Only for what no single field owns - the shop being closed, a
               throttle, a fault. Anything about a field is said under it. */ ?>
      <div class="form-err" id="omError"></div>

      <div class="field" data-field="link">
        <label for="omLink" id="omLinkLabel">Profile or post link</label>
        <input type="url" id="omLink" name="link" placeholder="https://..." required
               aria-describedby="omLinkHint">
        <small class="hint" id="omLinkHint">Make sure your account is public, not private<svg class="icon"><use href="#i-lock"></use></svg></small>
        <small class="err" id="omLinkErr" hidden></small>
      </div>

      <?php /* Optional, and not labelled as such. An order can be placed
               without it - the order code on the next screen is what the
               customer keeps, and the tracking page takes them back to it -
               but saying "optional" invites them to skip the one thing that
               lets us reach them if something needs asking. */ ?>
      <div class="field" data-field="whatsapp">
        <label for="omWa">WhatsApp Number</label>
        <input type="tel" id="omWa" name="whatsapp" inputmode="tel" maxlength="18">
        <small class="err" id="omWaErr" hidden></small>
      </div>

      <?php /* Off unless an admin asks for it. The order code and every update
               go to WhatsApp, so on a phone this was one more box between the
               customer and the button. */ ?>
      <?php if (setting('ask_email', '0') === '1'): ?>
        <div class="field" data-field="email">
          <label for="omEmail">Email <span class="opt-tag">optional</span></label>
          <input type="email" id="omEmail" name="email" placeholder="you@example.com"
                 aria-describedby="omEmailHint">
          <small class="hint" id="omEmailHint">For a receipt. Your order code is
            shown as soon as the order is placed either way.</small>
          <small class="err" id="omEmailErr" hidden></small>
        </div>
      <?php endif; ?>

      <button class="btn btn-primary btn-block btn-lg" type="submit" id="omSubmit">
        Place Order
      </button>

      <?php /* Under the button, not above it: it answers a worry somebody
               might still have at the moment of pressing, and a green box
               above the button was a thing to read before getting there. */ ?>
      <p class="safe-note">We never ask for your password.</p>
    </form>

    <?php /* Step two. Filled in by the script with the very markup the order
             page uses, so choosing a method and paying happen here rather
             than on another screen. Outside the form: showing this hides
             that one, and a panel inside it would be hidden with it. */ ?>
    <div class="mbody ompay" id="omPay" hidden></div>


  </div>
</div>
