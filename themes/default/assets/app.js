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
    // The words the modal shows follow the chosen service too. Without this
    // the panel changed service and the order box kept naming the first one.
    card.dataset.label   = option.dataset.label || option.dataset.name;
    // ...and so does the link it will ask for: two services in one category
    // can want different ones - reel views and story views are both "views".
    ['linkLabel', 'linkHint', 'linkHost', 'linkKind', 'linkPattern', 'linkNeeds']
      .forEach(function (key) {
        if (option.dataset[key] !== undefined) { card.dataset[key] = option.dataset[key]; }
      });

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

  // A text field focused as the modal opens brings the phone keyboard up with
  // it, which covers the price the customer opened the modal to check. So the
  // link box is only focused where focusing it costs nothing - a mouse and a
  // keyboard - and on a touch screen the dialog itself takes the focus
  // instead. Something inside has to hold it either way: that is what keeps
  // Escape and the tab trap working and what announces the dialog.
  var pointerIsFine = !window.matchMedia
    || window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  function openModal() {
    if (!modal) { return; }
    lastFocus = document.activeElement;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    var first = pointerIsFine
      ? modal.querySelector('#omLink')
      : modal.querySelector('.modal');
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
        fmt(qty) + ' ' + (card.dataset.label || card.dataset.name);
      document.getElementById('omPrice').textContent = price;
      document.getElementById('omLinkLabel').textContent = card.dataset.linkLabel;
      document.getElementById('omLink').placeholder      = card.dataset.linkHint;
      // Carried on the form so the check below knows what to insist on: the
      // host, and the shape of the link on it.
      form.dataset.linkHost    = card.dataset.linkHost || '';
      form.dataset.linkPattern = card.dataset.linkPattern || '';
      form.dataset.linkNeeds   = card.dataset.linkNeeds || '';
      form.dataset.platform = card.dataset.platform || '';
      clearErrors();
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

    // payment method: light the chosen tile and open only its step
    var pmRadio = event.target.closest('[data-pm-radio]');
    if (pmRadio) {
      document.querySelectorAll('[data-pay-opt]').forEach(function (opt) {
        opt.classList.toggle('on', opt.contains(pmRadio));
      });
      document.querySelectorAll('[data-pay-for]').forEach(function (step) {
        step.classList.toggle('on', step.getAttribute('data-pay-for') === pmRadio.value);
      });
      syncPaymentFields();
    }

    // Any button that sends the customer onward says so while it works. The
    // next thing is a page load - a gateway, or the order page coming back -
    // so there is nothing to put it back for, and a button that looks idle
    // gets pressed twice.
    var payGo = event.target.closest('[data-pay-go]');
    if (payGo && payGo.form && payGo.form.checkValidity()) {
      payGo.classList.add('btn-wait');
      payGo.textContent = 'Just a moment…';
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


  // ------------------------------------------------ payment method fields --
  // Each manual method asks its own questions. Only the chosen method's are
  // sent: the rest are disabled, which also stops a hidden required field
  // blocking the submit. Without this script every field posts and the server
  // reads only the ones the chosen method defines, so nothing is lost.
  function syncPaymentFields() {
    var chosen   = document.querySelector('[data-pm-radio]:checked');
    var chosenId = chosen ? chosen.value : null;

    document.querySelectorAll('[data-pf-for]').forEach(function (group) {
      var mine = group.getAttribute('data-pf-for') === chosenId;
      group.querySelectorAll('input, select, textarea').forEach(function (input) {
        input.disabled = !mine;
        input.required = mine && input.hasAttribute('data-pf-required');
      });
    });
  }
  syncPaymentFields();


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

  // The second half of the same rule: the right host, and the right kind of
  // link on it. The pattern comes from the card, which got it from the PHP,
  // so the check that runs while you type is the one the server will run.
  function linkPath(value) {
    var parsed;
    try { parsed = new URL(value); } catch (error) { return null; }

    var host = parsed.hostname.toLowerCase();
    var path = parsed.pathname || '/';

    // youtu.be/<id> is a watch link with the id where the path is, and
    // /watch only counts as a video when it carries one.
    if (!/youtu\.be$/.test(host) && /^\/watch/.test(path) && !parsed.searchParams.get('v')) {
      return '/watch-with-no-video';
    }
    return path;
  }

  function linkMatchesKind(value, pattern) {
    if (!pattern) { return true; }
    var path = linkPath(value);
    if (path === null) { return false; }
    try { return new RegExp(pattern, 'i').test(path); } catch (error) { return true; }
  }

  // An error belongs under the field it is about, in the place the hint was
  // standing: the person is looking there, and the panel does not grow by a
  // line it has to be scrolled to reach.
  function fieldError(input, message) {
    if (!input) { return; }
    var field = input.closest('.field');
    var hint  = field.querySelector('.hint');
    var slot  = field.querySelector('.err');
    if (!slot) { return; }

    if (message) {
      slot.textContent = message;
      slot.hidden = false;
      if (hint) { hint.hidden = true; }
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', slot.id);
    } else {
      slot.hidden = true;
      if (hint) { hint.hidden = false; input.setAttribute('aria-describedby', hint.id); }
      input.removeAttribute('aria-invalid');
    }
  }

  function clearErrors() {
    var banner = document.getElementById('omError');
    if (banner) { banner.classList.remove('show'); }
    ['omLink', 'omWa', 'omEmail'].forEach(function (id) {
      fieldError(document.getElementById(id), '');
    });
  }

  function checkLink() {
    var field = document.getElementById('omLink');
    if (!field || !form) { return true; }

    var host  = form.dataset.linkHost || '';
    var value = field.value.trim();
    if (value === '') {
      fieldError(field, '');
      return true;
    }

    // Short on purpose: an error sits where the hint was, and a message that
    // wraps to a second line is a message that pushes the button off a phone.
    if (!linkIsOnHost(value, host)) {
      fieldError(field, 'That link is not on ' + host + '.');
      return false;
    }
    if (!linkMatchesKind(value, form.dataset.linkPattern || '')) {
      fieldError(field, form.dataset.linkNeeds || 'That is the wrong kind of link.');
      return false;
    }

    fieldError(field, '');
    return true;
  }

  // ------------------------------------------------------- phone number --
  // The server allows fifteen digits, so the field stops accepting them at
  // fifteen rather than taking a longer number and refusing it afterwards.
  // Digits are what is counted, not characters: "+92 300 1234567" is a
  // fifteen-digit number written in eighteen, and the separators a person
  // types to keep their place are not the thing with a limit on it.
  var WHATSAPP_DIGITS = 15;

  function capNumber(input) {
    var value  = input.value;
    var digits = 0;
    var out    = '';

    for (var i = 0; i < value.length; i++) {
      var ch = value.charAt(i);
      if (ch >= '0' && ch <= '9') {
        if (digits === WHATSAPP_DIGITS) { continue; }
        digits++;
      } else if ('+ -()'.indexOf(ch) < 0) {
        continue;                     // a letter is not part of a number
      }
      out += ch;
    }

    if (out === value) { return; }

    // Only ever shorter, so the caret moves back by whatever was dropped -
    // which is nothing at all in the ordinary case of typing at the end.
    var caret = input.selectionStart;
    input.value = out;
    if (caret !== null && input.setSelectionRange) {
      caret = Math.max(0, caret - (value.length - out.length));
      input.setSelectionRange(caret, caret);
    }
  }

  document.addEventListener('input', function (event) {
    if (event.target.id === 'omLink') { checkLink(); }
    if (event.target.name === 'whatsapp') { capNumber(event.target); }
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

      // Stays in this state until the next screen replaces the page: where a
      // single gateway takes the payment, the answer to this request is the
      // page to go to, so there is nothing to put the button back for.
      button.disabled = true;
      button.classList.add('btn-wait');
      button.textContent = 'Placing your order…';
      clearErrors();

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.ok && data.code) {
            showPlaced(data);
            return;
          }
          var message = (data && data.error) || 'Something went wrong. Please try again.';
          // The server says which field it is about when it knows; only what
          // belongs to no field goes in the banner at the top.
          var inputs  = { link: 'omLink', whatsapp: 'omWa', email: 'omEmail' };
          var input   = data && inputs[data.field]
            ? document.getElementById(inputs[data.field])
            : null;

          if (input) {
            fieldError(input, message);
            input.focus();
          } else {
            error.textContent = message;
            error.classList.add('show');
          }
          button.disabled = false;
          button.classList.remove('btn-wait');
          button.textContent = 'Place Order';
        })
        .catch(function () {
          // Network trouble: fall back to a normal submit rather than dead-ending.
          useFetch = false;
          form.submit();
        });
    });
  }


  // ------------------------------------------------------- order placed --
  //
  // Straight to whatever comes next: the Paytm QR where one gateway takes
  // the payment, the order page where there is a choice of method to make.
  // The modal used to stop at a panel saying "Order placed" with a button
  // to carry on, which was a screen whose only purpose was to be left - and
  // the page it led to says the same things anyway, order code included.
  function showPlaced(data) {
    // One gateway and nothing else on: the server already began the payment
    // and this is the page to pay on.
    if (data.payment && data.payment.mode === 'redirect') {
      window.location.href = data.redirect;
      return;
    }

    var pay = document.getElementById('omPay');
    if (!pay || !data.pay) {
      window.location.href = data.redirect;
      return;
    }

    // The markup is the order page's own, and everything the script binds to
    // in it is delegated, so it behaves the same injected as it does served.
    pay.innerHTML = data.pay;
    form.hidden = true;
    pay.hidden = false;

    var heading = document.getElementById('orderModalTitle');
    if (heading) { heading.textContent = 'How would you like to pay?'; }

    var sub = modal.querySelector('.mhead p');
    if (sub) { sub.textContent = 'Order ' + data.code + ' · ' + data.amount; }

    modal.scrollTop = 0;
  }

  // ------------------------------------------- switching without a reload --
  // The platform strip and the category tabs are real links: without this
  // they just work, and search engines follow them. With it, the click is
  // intercepted, only the changed parts are fetched, and the address bar is
  // kept honest so Back still does what it should.
  var SWAP = { platforms: '#platStrip', tabs: '#catTabs', cards: '#serviceCards' };
  var loading = false;

  // A strip with more in it than fits scrolls, and the one that matters is
  // the chosen one - which is exactly the one that ends up half under the
  // bar's own rounded edge when the browser decides where to leave it. Put
  // it in the middle of what can be seen; scrollLeft only, so the page
  // itself never moves.
  function showChosen(selector) {
    var strip = document.querySelector(selector);
    if (!strip || strip.scrollWidth <= strip.clientWidth) { return; }

    var chosen = strip.querySelector('.active');
    if (!chosen) { return; }

    strip.scrollLeft = Math.max(0,
      chosen.offsetLeft - (strip.clientWidth - chosen.offsetWidth) / 2);
  }

  function showChosenTabs() {
    showChosen(SWAP.tabs);
    showChosen(SWAP.platforms);
  }

  showChosenTabs();
  window.addEventListener('resize', showChosenTabs);

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

    var cta = document.getElementById('heroCta');
    if (cta && data.ctaLabel) { cta.textContent = data.ctaLabel; }

    var svc = document.getElementById('svcTitle');
    if (svc && data.sectionTitle) { svc.textContent = data.sectionTitle; }

    if (data.title) { document.title = data.title; }

    showChosenTabs();
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

  // ------------------------------------------------------- copy buttons --
  // The order page has had these on the account number and the order code
  // since it was written, with nothing listening to them.
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-copy]');
    if (!button) { return; }

    var text = button.getAttribute('data-copy');
    var said = button.textContent;

    function done() {
      button.textContent = 'Copied';
      button.classList.add('is-copied');
      setTimeout(function () {
        button.textContent = said;
        button.classList.remove('is-copied');
      }, 1500);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, done);
      return;
    }

    // Older browsers, and any page not served over https.
    var carrier = document.createElement('textarea');
    carrier.value = text;
    carrier.setAttribute('readonly', '');
    carrier.style.position = 'fixed';
    carrier.style.left = '-9999px';
    document.body.appendChild(carrier);
    carrier.select();
    try { document.execCommand('copy'); } catch (error) { /* nothing else to try */ }
    document.body.removeChild(carrier);
    done();
  });
})();
