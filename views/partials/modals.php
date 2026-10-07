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

      <div class="form-err" id="omError"></div>

      <div class="field">
        <label for="omLink" id="omLinkLabel">Profile or post link</label>
        <input type="url" id="omLink" name="link" placeholder="https://..." required>
        <small>Make sure your account is public, not private.</small>
      </div>

      <div class="field">
        <label for="omWa">WhatsApp number</label>
        <input type="tel" id="omWa" name="whatsapp" placeholder="+92 300 1234567" required>
        <small>We send your order code and updates here.</small>
      </div>

      <div class="safe-note">
        <svg class="icon" style="font-size:18px;flex:none"><use href="#i-shield"></use></svg>
        <span>We never ask for your password. Only the public link is needed.</span>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit" id="omSubmit">
        Continue to payment &rarr;
      </button>
    </form>
  </div>
</div>
