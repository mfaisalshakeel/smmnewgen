/* Admin panel behaviour. Small enough to stay in one file. */
(function () {
  'use strict';

  // --- mobile sidebar ------------------------------------------------------
  function openSide()  { document.body.classList.add('side-open'); }
  function closeSide() { document.body.classList.remove('side-open'); }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-side-open]'))  { openSide(); }
    if (event.target.closest('[data-side-close]')) { closeSide(); }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { closeSide(); }
  });

  // --- confirm destructive actions ----------------------------------------
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  // --- copy buttons --------------------------------------------------------
  document.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-copy]');
    if (!btn) { return; }
    var text = btn.getAttribute('data-copy');
    var done = function () {
      var original = btn.textContent;
      btn.textContent = 'Copied';
      setTimeout(function () { btn.textContent = original; }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, done);
    } else {
      done();
    }
  });
})();
