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

  // Matches money() on the server: a symbol ending in a letter needs air
  // before the figure, a glyph like $ does not.
  var CURRENCY_GAP = /[^\W\d_]$/.test(CURRENCY) ? '\u00a0' : '';

  function money(amount) {
    var text = (Math.round(amount * 100) / 100).toFixed(2).replace(/\.00$/, '');
    return CURRENCY + CURRENCY_GAP + text.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  // ---------------------------------------------------------------- price --
  // Type a quantity, the amount follows. Limits are only mentioned when the
  // number actually breaks one.
  function recalc(card) {
    var input = card.querySelector('[data-qty]');
    if (!input) { return; }        // a package card: its quantity is fixed

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

  // ------------------------------------------------- custom order picker --
  // The panel is an ordinary card, so choosing a different service rewrites
  // its data attributes rather than re-rendering anything: the price maths
  // and the order modal keep working on it unchanged.
  document.addEventListener('change', function (event) {
    var select = event.target.closest('[data-service-pick]');
    if (!select) { return; }

    var option = select.options[select.selectedIndex];
    var card   = select.closest('.card');
    var input  = card.querySelector('[data-qty]');

    card.dataset.service = option.value;
    card.dataset.rate    = option.dataset.rate;
    card.dataset.min     = option.dataset.min;
    card.dataset.max     = option.dataset.max;
    card.dataset.name    = option.dataset.name;

    var min = parseInt(option.dataset.min, 10);
    var max = parseInt(option.dataset.max, 10);
    input.min = min;
    input.max = max;

    // The number on screen may be outside what this service accepts; move it
    // to the nearest end rather than leaving an error the customer did not
    // cause.
    var qty = parseInt(input.value, 10);
    if (isNaN(qty) || qty < min) { input.value = min; }
    else if (qty > max)          { input.value = max; }

    var lo = card.querySelector('[data-range-min]');
    var hi = card.querySelector('[data-range-max]');
    if (lo) { lo.textContent = fmt(min); }
    if (hi) { hi.textContent = fmt(max); }

    var delivery = card.querySelector('[data-delivery-text]');
    var deliveryLine = delivery && delivery.closest('.qp-del');
    if (deliveryLine) {
      delivery.textContent = option.dataset.delivery || '';
      deliveryLine.hidden = !option.dataset.delivery;
    }

    recalc(card);
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
      var card  = orderBtn.closest('.card');
      var pkg   = card.dataset.package || '';
      var typed = card.querySelector('[data-qty]');
      var qty, price;

      // What tells the two cards apart is whether there is a quantity to
      // type, not whether there is a package id: a generated tier is a fixed
      // quantity with no package behind it.
      if (!typed) {
        qty   = parseInt(card.dataset.qty, 10);
        price = card.dataset.price;
      } else {
        var min = parseInt(card.dataset.min, 10);
        var max = parseInt(card.dataset.max, 10);
        qty = parseInt(typed.value, 10);

        if (isNaN(qty) || qty < min || qty > max) {
          recalc(card);
          typed.focus();
          return;
        }
        price = card.querySelector('[data-price]').textContent;
      }

      document.getElementById('omService').value  = card.dataset.service;
      document.getElementById('omPackage').value  = pkg;
      document.getElementById('omQuantity').value = qty;
      // The card above already says what this is. Repeating a raw provider
      // name - "[ Max 100K ] | Old Accounts | No Refill | Instant Start" - is
      // noise at the point of paying, so the plain words win where there are
      // any.
      document.getElementById('omTitle').textContent =
        fmt(qty) + ' × ' + (card.dataset.label || card.dataset.name);
      document.getElementById('omPrice').textContent = price;
      document.getElementById('omDelivery').textContent =
        (card.querySelector('.qp-del') || { textContent: '' }).textContent.trim() || 'Starts shortly';
      document.getElementById('omLinkLabel').textContent = card.dataset.linkLabel;
      document.getElementById('omLink').placeholder      = card.dataset.linkHint;
      // Carried on the form so the check below knows which host to insist on.
      form.dataset.linkHost = card.dataset.linkHost || '';
      form.dataset.platform = card.dataset.platform || '';
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


  // --------------------------------------------------------- link check --
  // The same rule the server applies: the host has to BE the platform's host
  // or a subdomain of it. "Contains" would let instagram.com.example.net
  // through. This only moves the answer earlier - the server still decides.
  function linkIsOnHost(value, host) {
    if (!host) { return true; }
    var parsed;
    try { parsed = new URL(value); } catch (error) { return false; }

    var actual = parsed.hostname.toLowerCase().replace(/^www\./, '');
    host = host.toLowerCase().replace(/^www\./, '');
    return actual === host || actual.endsWith('.' + host);
  }

  function checkLink() {
    var field = document.getElementById('omLink');
    var error = document.getElementById('omError');
    if (!field || !form) { return true; }

    var host  = form.dataset.linkHost || '';
    var value = field.value.trim();
    if (value === '' || linkIsOnHost(value, host)) {
      field.removeAttribute('aria-invalid');
      if (error.dataset.from === 'link') { error.classList.remove('show'); }
      return true;
    }

    field.setAttribute('aria-invalid', 'true');
    // Named rather than articled, so neither 'a Instagram' nor a guess at
    // which article a platform's name wants.
    error.textContent = (form.dataset.platform || 'These') + ' links are on ' + host
      + '. That one is not.';
    error.dataset.from = 'link';
    error.classList.add('show');
    return false;
  }

  document.addEventListener('input', function (event) {
    if (event.target.id === 'omLink') { checkLink(); }
  });
  // ------------------------------------------------------- submit (ajax) --
  // Posting over fetch keeps the typed link and number on screen when the
  // server rejects something. Without JS the same form posts normally and the
  // server answers with a redirect, so nothing is lost.
  if (form) {
    var useFetch = true;

    form.addEventListener('submit', function (event) {
      if (!checkLink()) {
        event.preventDefault();
        document.getElementById('omLink').focus();
        return;
      }
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

  // ------------------------------------------- switching without a reload --
  // The platform strip and the category tabs are real links: without this
  // they just work, and search engines follow them. With it, the click is
  // intercepted, only the changed parts are fetched, and the address bar is
  // kept honest so Back still does what it should.
  var SWAP = { platforms: '#platStrip', tabs: '#catTabs', cards: '#serviceCards' };
  var loading = false;

  function skeleton() {
    var cards = document.querySelector(SWAP.cards);
    if (!cards) { return; }
    var count = Math.max(1, cards.querySelectorAll('.card').length);
    var html = '';
    for (var i = 0; i < count; i++) {
      html += '<div class="card card-skeleton" aria-hidden="true">'
            +   '<span class="sk sk-badge"></span>'
            +   '<span class="sk sk-title"></span>'
            +   '<span class="sk sk-line"></span>'
            +   '<span class="sk sk-line short"></span>'
            +   '<div class="sk-row"><span class="sk sk-box"></span><span class="sk sk-box"></span></div>'
            +   '<span class="sk sk-line"></span>'
            +   '<span class="sk sk-button"></span>'
            + '</div>';
    }
    cards.innerHTML = html;
    cards.classList.add('is-loading');
  }

  function apply(data) {
    Object.keys(SWAP).forEach(function (key) {
      var target = document.querySelector(SWAP[key]);
      if (target && data[key]) {
        target.outerHTML = data[key];
      }
    });

    var hero = document.getElementById('heroTitle');
    if (hero && data.heroTitle) {
      var grad = hero.querySelector('.grad');
      if (grad) { grad.textContent = data.heroTitle; }
    }

    var cta = document.getElementById('heroCta');
    if (cta && data.ctaLabel) { cta.textContent = data.ctaLabel; }

    var svc = document.getElementById('svcTitle');
    if (svc && data.heroTitle) { svc.textContent = data.heroTitle; }

    if (data.title) { document.title = data.title; }

    document.querySelectorAll('.card').forEach(recalc);
  }

  function load(url, push) {
    if (loading) { return; }
    loading = true;
    skeleton();

    fetch(url, { headers: { 'X-Fragment': '1' }, credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) { throw new Error('fetch failed'); }
        return response.json();
      })
      .then(function (data) {
        apply(data);
        if (push) { history.pushState({ smm: true }, '', data.url || url); }
        loading = false;
      })
      .catch(function () {
        // Anything unexpected: let the browser do what it would have done.
        loading = false;
        window.location.href = url;
      });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('#platStrip a, #catTabs a');
    if (!link) { return; }

    // Leave the modified clicks alone - people use them to open new tabs.
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
      return;
    }
    if (link.hostname !== window.location.hostname) { return; }

    event.preventDefault();
    if (link.classList.contains('active')) { return; }
    load(link.href, true);
  });

  window.addEventListener('popstate', function () {
    load(window.location.href, false);
  });

  // Set every card's price on load, in case the browser restored a value.
  document.querySelectorAll('.card').forEach(recalc);
})();
