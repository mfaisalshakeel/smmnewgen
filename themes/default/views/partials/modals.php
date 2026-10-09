<?php /** Order modal. The track form is a real page, so it needs no modal. */ ?>
<div class="overlay" id="orderModal" data-overlay>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
    <div class="mhead">
      <div>
        <h3 id="orderModalTitle">Complete your order</h3>
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

      <div class="summary">
        <div class="si"><svg class="icon" id="omIcon"><use href="#i-rocket"></use></svg></div>
        <div>
          <b id="omTitle">&nbsp;</b>
          <small id="omDelivery">&nbsp;</small>
        </div>
        <span class="amt" id="omPrice">&nbsp;</span>
      </div>

      <?php /* Only for what no single field owns - the shop being closed, a
               throttle, a fault. Anything about a field is said under it. */ ?>
      <div class="form-err" id="omError"></div>

      <div class="field" data-field="link">
        <label for="omLink" id="omLinkLabel">Profile or post link</label>
        <input type="url" id="omLink" name="link" placeholder="https://..." required
               aria-describedby="omLinkHint">
        <small class="hint" id="omLinkHint">Make sure your account is public, not private.</small>
        <small class="err" id="omLinkErr" hidden></small>
      </div>

      <div class="field" data-field="whatsapp">
        <label for="omWa">WhatsApp number</label>
        <input type="tel" id="omWa" name="whatsapp" placeholder="+92 300 1234567" required
               aria-describedby="omWaHint">
        <small class="hint" id="omWaHint">We send your order code and updates here.</small>
        <small class="err" id="omWaErr" hidden></small>
      </div>

      <div class="field" data-field="email">
        <label for="omEmail">Email <span class="opt">optional</span></label>
        <input type="email" id="omEmail" name="email" placeholder="you@example.com"
               aria-describedby="omEmailHint">
        <small class="hint" id="omEmailHint">For a receipt. The order code still
          goes to WhatsApp.</small>
        <small class="err" id="omEmailErr" hidden></small>
      </div>

      <div class="safe-note">
        <svg class="icon" style="font-size:18px;flex:none"><use href="#i-shield"></use></svg>
        <span>We never ask for your password. Only the public link is needed.</span>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit" id="omSubmit">
        Continue to payment &rarr;
      </button>
    </form>

  <!-- Outside the form: placing the order hides the form, and the panel
       that reports it must survive that. -->
    <div class="om-done" id="omDone" hidden>
      <div class="om-done-head">
        <span class="om-tick"><svg class="icon"><use href="#i-check"></use></svg></span>
        <b>Order placed</b>
      </div>
      <dl class="om-done-rows">
        <div><dt>Order code</dt><dd id="omDoneCode" class="mono"></dd></div>
        <div><dt>Amount</dt><dd id="omDoneAmount"></dd></div>
        <div><dt>Status</dt><dd id="omDoneStatus"></dd></div>
      </dl>
      <p class="om-done-note" id="omDoneNote"></p>
      <a class="btn btn-primary btn-block btn-lg" id="omDoneGo" href="#">Continue to payment &rarr;</a>
    </div>

  </div>
</div>
