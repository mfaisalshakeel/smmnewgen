/* Front-site behaviour.
   Everything here is an enhancement: the platform strip and the category tabs
   are real links, the order form is a real POST, and the site works with this
   file blocked. */
(function () {
  'use strict';

  var CURRENCY = document.documentElement.getAttribute('data-currency') || '';

  function fmt(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function money(amount) {
    var text = (Math.round(amount * 100) / 100).toFixed(2).replace(/\.00$/, '');
    return CURRENCY + text.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  // ---------------------------------------------------------------- price --
  // Type a quantity, the amount follows. Limits are only mentioned when the
  // number actually breaks one.
  function recalc(card) {
    var input = card.querySelector('[data-qty]');
    var rate  = parseFloat(card.dataset.rate);
    var min   = parseInt(card.dataset.min, 10);
    var max   = parseInt(card.dataset.max, 10);
    var qty   = parseInt(input.value, 10);
    var error = card.querySelector('[data-qty-err]');

    var effective = isNaN(qty) || qty < 0 ? 0 : Math.min(qty, max);
    card.querySelector('[data-price]').textContent = money(effective / 1000 * rate);

    if (!isNaN(qty) && qty > 0 && qty < min) {
      error.textContent = 'Minimum for this service is ' + fmt(min) + '.';
      error.classList.add('show');
    } else if (qty > max) {
      error.textContent = 'Maximum for this service is ' + fmt(max) + '.';
      error.classList.add('show');
    } else {
      error.classList.remove('show');
    }
  }

  document.addEventListener('input', function (event) {
    if (event.target.matches('[data-qty]')) {
      recalc(event.target.closest('.card'));
    }
  });

  // ---------------------------------------------------------------- modal --
  var modal   = document.getElementById('orderModal');
  var form    = document.getElementById('orderForm');
  var lastFocus = null;

  function openModal() {
    if (!modal) { return; }
    lastFocus = document.activeElement;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    var first = modal.querySelector('#omLink');
    if (first) { first.focus(); }
  }

  function closeModal() {
    if (!modal) { return; }
    modal.classList.remove('show');
    document.body.style.overflow = '';
    if (lastFocus) { lastFocus.focus(); }
  }

  document.addEventListener('click', function (event) {
    // open
    var orderBtn = event.target.closest('[data-order]');
    if (orderBtn) {
      var card = orderBtn.closest('.card');
      var min  = parseInt(card.dataset.min, 10);
      var max  = parseInt(card.dataset.max, 10);
      var qty  = parseInt(card.querySelector('[data-qty]').value, 10);

      if (isNaN(qty) || qty < min || qty > max) {
        recalc(card);
        card.querySelector('[data-qty]').focus();
        return;
      }

      document.getElementById('omService').value  = card.dataset.service;
      document.getElementById('omQuantity').value = qty;
      document.getElementById('omTitle').textContent =
        fmt(qty) + ' × ' + card.dataset.name;
      document.getElementById('omPrice').textContent =
        card.querySelector('[data-price]').textContent;
      document.getElementById('omDelivery').textContent =
        (card.querySelector('.qp-del') || { textContent: '' }).textContent.trim() || 'Starts shortly';
      document.getElementById('omLinkLabel').textContent = card.dataset.linkLabel;
      document.getElementById('omLink').placeholder      = card.dataset.linkHint;
      document.getElementById('omError').classList.remove('show');
      openModal();
      return;
    }

    // close
    if (event.target.closest('[data-close]') || event.target.matches('[data-overlay]')) {
      closeModal();
    }

    // mobile drawer
    if (event.target.closest('[data-drawer]')) {
      var drawer = document.getElementById('drawer');
      if (drawer) { drawer.classList.toggle('show'); }
    }

    // payment method: move the open panel to whichever radio is now checked
    var pmRadio = event.target.closest('[data-pm-radio]');
    if (pmRadio) {
      document.querySelectorAll('[data-pm]').forEach(function (block) {
        block.classList.toggle('on', block.contains(pmRadio));
      });
    }

    // FAQ accordion
    var faqBtn = event.target.closest('[data-faq]');
    if (faqBtn) {
      var item = faqBtn.parentElement;
      var body = item.querySelector('.fa');
      var wasOpen = item.classList.contains('open');
      document.querySelectorAll('.fitem').forEach(function (other) {
        other.classList.remove('open');
        other.querySelector('.fa').style.maxHeight = 0;
      });
      if (!wasOpen) {
        item.classList.add('open');
        body.style.maxHeight = body.scrollHeight + 'px';
      }
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { closeModal(); }
  });

  // ------------------------------------------------------- submit (ajax) --
  // Posting over fetch keeps the typed link and number on screen when the
  // server rejects something. Without JS the same form posts normally and the
  // server answers with a redirect, so nothing is lost.
  if (form) {
    var useFetch = true;

    form.addEventListener('submit', function (event) {
      if (!useFetch) { return; }        // the retry below submits normally
      event.preventDefault();

      var button = document.getElementById('omSubmit');
      var error  = document.getElementById('omError');
      button.disabled = true;
      button.textContent = 'Placing order…';
      error.classList.remove('show');

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.ok && data.redirect) {
            window.location.href = data.redirect;
            return;
          }
          error.textContent = (data && data.error) || 'Something went wrong. Please try again.';
          error.classList.add('show');
          button.disabled = false;
          button.innerHTML = 'Continue to payment &rarr;';
        })
        .catch(function () {
          // Network trouble: fall back to a normal submit rather than dead-ending.
          useFetch = false;
          form.submit();
        });
    });
  }

  // Set every card's price on load, in case the browser restored a value.
  document.querySelectorAll('.card').forEach(recalc);
})();
