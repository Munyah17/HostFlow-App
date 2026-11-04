/* HostFlow front-end behaviour (vanilla JS, no build step). */
(function () {
  'use strict';

  var toggle = document.getElementById('nav-toggle');

  // Close the mobile sidebar after navigating or pressing Escape.
  document.querySelectorAll('.nav a').forEach(function (a) {
    a.addEventListener('click', function () { if (toggle) toggle.checked = false; });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (toggle) toggle.checked = false;
      closeModal();
    }
  });

  // Toast notifications (uses the mockup's .toast element).
  function toast(message, isError) {
    var t = document.getElementById('toast');
    if (!t) return;
    t.textContent = message;
    t.classList.toggle('error', !!isError);
    t.classList.add('show');
    clearTimeout(window.hostFlowToastTimer);
    window.hostFlowToastTimer = setTimeout(function () { t.classList.remove('show'); }, 4000);
  }
  window.hostflowToast = toast;

  // Confirmation modal for destructive actions: <form data-confirm="Message">.
  var modal = document.getElementById('confirm-modal');
  var pendingForm = null;
  function closeModal() {
    if (modal) modal.classList.remove('open');
    pendingForm = null;
  }
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.confirm && !form.dataset.confirmed && modal) {
      e.preventDefault();
      pendingForm = form;
      modal.querySelector('[data-modal-message]').textContent = form.dataset.confirm;
      var ok = modal.querySelector('[data-modal-ok]');
      ok.textContent = form.dataset.confirmButton || 'Confirm';
      ok.className = 'btn ' + (form.dataset.confirmStyle || 'danger');
      var input = modal.querySelector('[data-modal-input]');
      var wrap = modal.querySelector('[data-modal-input-wrap]');
      if (form.dataset.confirmType) {
        wrap.style.display = 'block';
        wrap.querySelector('span').textContent = 'Type "' + form.dataset.confirmType + '" to confirm';
        input.value = '';
      } else {
        wrap.style.display = 'none';
      }
      modal.classList.add('open');
      ok.focus();
      return;
    }
    // Prevent double submissions.
    var btn = form.querySelector('button[type=submit]:not([data-no-disable])');
    if (btn && !form.dataset.submitted) {
      form.dataset.submitted = '1';
      setTimeout(function () { btn.disabled = true; }, 0);
    } else if (btn && form.dataset.submitted) {
      e.preventDefault();
    }
  }, true);
  if (modal) {
    modal.querySelector('[data-modal-cancel]').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    modal.querySelector('[data-modal-ok]').addEventListener('click', function () {
      if (!pendingForm) return;
      var expected = pendingForm.dataset.confirmType;
      if (expected) {
        var typed = modal.querySelector('[data-modal-input]').value.trim();
        if (typed !== expected) { toast('Confirmation text does not match.', true); return; }
        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'confirm_text'; hidden.value = typed;
        pendingForm.appendChild(hidden);
      }
      var f = pendingForm;
      f.dataset.confirmed = '1';
      closeModal();
      if (f.requestSubmit) f.requestSubmit(); else f.submit();
    });
  }

  // Client-side quick filter for tables: <input data-table-filter="#table-id">.
  document.querySelectorAll('[data-table-filter]').forEach(function (input) {
    var table = document.querySelector(input.dataset.tableFilter);
    if (!table) return;
    input.addEventListener('input', function () {
      var q = input.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) === -1 ? 'none' : '';
      });
    });
  });

  // Auto-submit filter selects.
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form && el.form.submit(); });
  });

  // Checkbox reveals a hidden section: <input data-toggle="x"> + <div data-toggle-target="x">.
  document.querySelectorAll('[data-toggle]').forEach(function (el) {
    var target = document.querySelector('[data-toggle-target="' + el.dataset.toggle + '"]');
    if (!target) return;
    var apply = function () { target.hidden = !el.checked; };
    el.addEventListener('change', apply);
    apply();
  });

  // Order form: update price when plan/cycle changes.
  document.querySelectorAll('[data-plan-select]').forEach(function (form) {
    var plan = form.querySelector('[name=plan_id]');
    var cycle = form.querySelector('[name=billing_cycle]');
    var out = form.querySelector('[data-price-output]');
    function update() {
      if (!plan || !cycle) return;
      var opt = plan.options ? plan.options[plan.selectedIndex] : form.querySelector('[name=plan_id]:checked');
      if (!opt) return;
      var prices = {};
      try { prices = JSON.parse(opt.dataset.prices || '{}'); } catch (e) {}
      var current = cycle.value;
      Array.prototype.forEach.call(cycle.options, function (o) {
        var available = prices.hasOwnProperty(o.value);
        o.disabled = !available;
        o.textContent = o.dataset.label + (available ? ' — $' + Number(prices[o.value]).toFixed(2) : ' (not offered)');
      });
      if (!prices.hasOwnProperty(current)) {
        var first = Object.keys(prices)[0];
        if (first) cycle.value = first;
      }
      if (out) out.textContent = prices[cycle.value] !== undefined ? '$' + Number(prices[cycle.value]).toFixed(2) : '—';
    }
    form.addEventListener('change', update);
    update();
  });

  // Payment status polling on the payment return page.
  var poll = document.querySelector('[data-payment-poll]');
  if (poll) {
    var attempts = 0;
    var check = function () {
      attempts++;
      fetch(poll.dataset.paymentPoll, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          poll.querySelector('[data-status]').textContent = data.message || data.status;
          if (data.status === 'completed' || data.status === 'failed' || data.status === 'cancelled') {
            setTimeout(function () { window.location.href = poll.dataset.redirect; }, 1500);
          } else if (attempts < 40) {
            setTimeout(check, 6000);
          }
        })
        .catch(function () { if (attempts < 40) setTimeout(check, 8000); });
    };
    setTimeout(check, 2500);
  }

  // Copy-to-clipboard buttons.
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (navigator.clipboard) navigator.clipboard.writeText(btn.dataset.copy).then(function () { toast('Copied to clipboard'); });
    });
  });

  // Show flash message as a toast too (for long pages).
  var flash = document.querySelector('[data-flash-toast]');
  if (flash) toast(flash.dataset.flashToast, flash.dataset.flashError === '1');
})();
