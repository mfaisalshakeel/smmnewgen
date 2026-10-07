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

  // --- the updater ---------------------------------------------------------
  //
  // Each step is its own request. The browser asks for the plan first, so
  // pressing the button twice cannot run the same migration twice: the
  // second plan comes back without it.
  (function () {
    var panel = document.querySelector('[data-update]');
    if (!panel) { return; }

    var form    = panel.querySelector('[data-update-form]');
    var button  = panel.querySelector('[data-update-go]');
    var barWrap = panel.querySelector('[data-bar-wrap]');
    var bar     = panel.querySelector('[data-bar]');
    var note    = panel.querySelector('[data-bar-note]');
    var label   = panel.querySelector('[data-bar-label]');
    var count   = panel.querySelector('[data-bar-count]');
    var errBox  = panel.querySelector('[data-update-error]');
    var doneBox = panel.querySelector('[data-update-done]');
    var token   = panel.getAttribute('data-token');

    function post(url, body) {
      body.append('_token', token);
      return fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'fetch' },
        body: body,
        credentials: 'same-origin'
      }).then(function (response) {
        if (!response.ok) { throw new Error('Server returned ' + response.status); }
        return response.json();
      });
    }

    function row(key) {
      return panel.querySelector('.upd-step[data-key="' + key + '"]');
    }

    function progress(done, total, busy) {
      var percent = total ? Math.round((done / total) * 100) : 100;
      bar.style.width = percent + '%';
      bar.classList.toggle('busy', !!busy);
      count.textContent = done + ' / ' + total;
    }

    function fail(message) {
      bar.classList.remove('busy');
      bar.classList.add('bad');
      errBox.textContent = message;
      errBox.hidden = false;
      button.removeAttribute('aria-busy');
      button.innerHTML = 'Try again';
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      button.setAttribute('aria-busy', 'true');
      button.innerHTML = '<span class="spin"></span> Updating\u2026';
      errBox.hidden = true;
      barWrap.hidden = false;
      note.hidden = false;
      label.textContent = 'Working out what to do\u2026';

      post(panel.getAttribute('data-plan'), new FormData()).then(function (plan) {
        var steps = plan.steps || [];
        var total = steps.length;
        var index = 0;

        // Rows the plan no longer contains are already done; grey them out
        // rather than leave them looking as if they are waiting their turn.
        panel.querySelectorAll('.upd-step').forEach(function (element) {
          var wanted = steps.some(function (step) {
            return step.key === element.getAttribute('data-key');
          });
          if (!wanted) { element.classList.add('ok'); }
        });

        function next() {
          if (index >= total) {
            progress(total, total, false);
            label.textContent = 'Done \u2014 now on version ' + plan.to;
            button.hidden = true;
            doneBox.hidden = false;
            return;
          }

          var step = steps[index];
          var element = row(step.key);
          if (element) { element.classList.add('on'); }
          label.textContent = step.label;
          progress(index, total, true);

          var body = new FormData();
          body.append('key', step.key);

          post(panel.getAttribute('data-step'), body).then(function (result) {
            if (element) {
              element.classList.remove('on');
              element.classList.add(result.ok ? 'ok' : 'bad');
              var slot = element.querySelector('[data-note]');
              if (slot) { slot.textContent = result.note || ''; }
            }

            if (!result.ok) {
              fail((result.errors || ['That step failed.']).join(' '));
              return;
            }

            index++;
            // A breath between steps, so a fast database still reads as
            // progress rather than one instant jump.
            setTimeout(next, 180);
          }).catch(function (error) {
            if (element) { element.classList.remove('on'); element.classList.add('bad'); }
            fail(error.message);
          });
        }

        next();
      }).catch(function (error) {
        fail(error.message);
      });
    });
  })();
})();
