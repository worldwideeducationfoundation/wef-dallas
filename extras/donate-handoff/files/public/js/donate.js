/*
 * The donation form on donate.php (Bright Beginnings, paid on Stripe).
 *
 * Port of the tested Next.js app/donate/DonationForm.tsx. Shows the panel
 * for the chosen way to give, keeps the "Your gift" total up to date, shows
 * inline errors after the first submit, and posts the choices as JSON to
 * api/checkout.php, which prices them on the server and answers with the
 * Stripe Checkout URL.
 *
 * The checks below mirror includes/donate/pricing.php so the donor sees
 * mistakes before leaving the page. They decide nothing: the server runs its
 * own copy on every request, and never takes a price from here.
 */
(function () {
  'use strict';

  var form = document.getElementById('donate-form');
  if (!form) return;

  var CFG = JSON.parse(form.getAttribute('data-config') || '{}');
  var EMAIL = /^(?!\.)(?!.*\.\.)([A-Za-z0-9_'+\-\.]*)[A-Za-z0-9_+-]@([A-Za-z0-9][A-Za-z0-9\-]*\.)+[A-Za-z]{2,}$/;

  var $ = function (sel, root) { return (root || form).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || form).querySelectorAll(sel)); };

  var attempted = false;
  var busy = false;
  var serverErrors = {};

  /* ---------------------------------------------------------------- */
  /* Money                                                             */
  /* ---------------------------------------------------------------- */

  function money(cents) {
    var whole = cents % 100 === 0;
    return '$' + (cents / 100).toLocaleString('en-US', {
      minimumFractionDigits: whole ? 0 : 2,
      maximumFractionDigits: 2
    });
  }

  /** "25", "25.5" or "$1,250.00" -> cents; null if it is not a clean amount. */
  function dollarsToCents(input) {
    var cleaned = String(input).trim().replace(/^\$/, '').replace(/,/g, '');
    if (!/^\d+(\.\d{1,2})?$/.test(cleaned)) return null;
    return Math.round(Number(cleaned) * 100);
  }

  /* ---------------------------------------------------------------- */
  /* Reading the form                                                  */
  /* ---------------------------------------------------------------- */

  function checked(name) {
    var el = $('input[name="' + name + '"]:checked');
    return el ? el.value : '';
  }

  function option() { return checked('option') || 'center'; }

  /** The centers box in the visible panel. */
  function centersInput() {
    return $('[data-panel="' + option() + '"] [data-centers]') || $('[data-centers]');
  }

  /** What the donor has chosen so far, in the shape the server expects. */
  function toSelection() {
    var opt = option();
    var cText = (centersInput() || { value: '' }).value.trim();
    var centers = cText === '' ? null : Number(cText);

    if (opt === 'center') {
      var wantsNaming = $('#field-wantsNaming').checked;
      var name = $('#field-centerName').value;
      var sel = { option: 'center', plan: checked('plan'), centers: centers, wantsNaming: wantsNaming };
      if (wantsNaming && name.trim()) sel.centerName = name;
      return sel;
    }
    if (opt === 'parts') {
      // Config order, so the receipt lists parts the same way every time.
      var picked = $$('input[name="items"]:checked').map(function (i) { return i.value; });
      var items = CFG.parts.filter(function (p) { return picked.indexOf(p.id) !== -1; }).map(function (p) { return p.id; });
      var otherText = $('#field-otherAmountCents').value;
      var s = { option: 'parts', items: items, centers: centers };
      if (otherText.trim() !== '') s.otherAmountCents = dollarsToCents(otherText); // null = not a clean amount
      return s;
    }
    return { option: 'custom', frequency: checked('frequency'), amountCents: dollarsToCents($('#field-amountCents').value) };
  }

  function toDetails() {
    return {
      donorName: $('#field-donorName').value,
      donorEmail: $('#field-donorEmail').value,
      recognitionName: $('#field-recognitionName').value,
      honorType: checked('honorType') || 'none',
      honorName: $('#field-honorName').value,
      updatesOptIn: $('#field-updatesOptIn').checked
    };
  }

  /* ---------------------------------------------------------------- */
  /* The same checks as includes/donate/pricing.php                    */
  /* ---------------------------------------------------------------- */

  function isWhole(v) { return typeof v === 'number' && isFinite(v) && Math.floor(v) === v; }

  function amountError(v, typeMessage) {
    if (typeof v !== 'number' || !isFinite(v)) return typeMessage;
    if (!isWhole(v)) return 'Enter an amount in dollars and cents.';
    if (v < CFG.custom.minCents) return 'The smallest gift is ' + money(CFG.custom.minCents) + '.';
    if (v > CFG.custom.maxCents) return 'Online gifts are limited to ' + money(CFG.custom.maxCents) + '. Please contact us for a larger gift.';
    return null;
  }

  function centersError(v) {
    if (typeof v !== 'number' || !isFinite(v)) return 'Choose how many centers.';
    if (!isWhole(v)) return 'Choose a whole number of centers.';
    if (v < 1) return 'Choose at least one center.';
    if (v > CFG.maxCenters) return 'Please contact us to support more than ' + CFG.maxCenters + ' centers.';
    return null;
  }

  function validateSelection(s) {
    var errors = {};
    var add = function (k, m) { if (m && !errors[k]) errors[k] = m; };

    if (s.option === 'center') {
      if (!CFG.plans.some(function (p) { return p.id === s.plan; })) add('plan', 'Choose how you would like to give.');
      add('centers', centersError(s.centers));
      if (s.centerName !== undefined && s.centerName.trim().length > 100) add('centerName', 'That name is too long.');
    } else if (s.option === 'parts') {
      add('centers', centersError(s.centers));
      if (s.otherAmountCents !== undefined) add('otherAmountCents', amountError(s.otherAmountCents, 'Enter an amount in dollars and cents.'));
      if (!Object.keys(errors).length && s.items.length === 0 && s.otherAmountCents === undefined) {
        add('items', 'Choose at least one part of a center, or enter another amount.');
      }
    } else {
      if (s.frequency !== 'once' && s.frequency !== 'monthly') add('frequency', 'Choose one-time or monthly.');
      add('amountCents', amountError(s.amountCents, 'Enter an amount.'));
    }
    return errors;
  }

  function validateDetails(d) {
    var errors = {};
    var add = function (k, m) { if (m && !errors[k]) errors[k] = m; };
    var name = d.donorName.trim();
    if (name === '') add('donorName', 'Please enter your name.');
    else if (name.length > 100) add('donorName', 'That name is too long.');
    var email = d.donorEmail.trim();
    if (!EMAIL.test(email)) add('donorEmail', 'Please enter a valid email address.');
    else if (email.length > 254) add('donorEmail', 'That email address is too long.');
    if (d.recognitionName.trim().length > 100) add('recognitionName', 'That name is too long.');
    if (d.honorName.trim().length > 100) add('honorName', 'That name is too long.');
    return errors;
  }

  function validateDonation(sel, det) {
    var selErrors = validateSelection(sel);
    var detErrors = validateDetails(det);
    var errors = Object.assign({}, selErrors, detErrors);
    if (!Object.keys(detErrors).length) {
      if (!Object.keys(selErrors).length && sel.option === 'center' && det.recognitionName.trim() === '') {
        errors.recognitionName = 'Please tell us how your name should appear on the center plaque.';
      }
      if (det.honorType !== 'none' && det.honorName.trim() === '') {
        errors.honorName = 'Please enter the name of the person.';
      }
    }
    return errors;
  }

  /* ---------------------------------------------------------------- */
  /* What it costs (display only; the server prices the real charge)   */
  /* ---------------------------------------------------------------- */

  function buildQuote(s) {
    if (s.option === 'center') {
      var plan = CFG.plans.filter(function (p) { return p.id === s.plan; })[0];
      var monthly = plan.installments > 1;
      var per = plan.perPaymentCents * s.centers;
      return {
        monthly: monthly, perPaymentCents: per, installments: plan.installments, totalCents: per * plan.installments,
        lines: [{ label: 'Bright Beginnings Center', unit: plan.perPaymentCents, quantity: s.centers }]
      };
    }
    if (s.option === 'parts') {
      var lines = s.items.map(function (id) {
        var part = CFG.parts.filter(function (p) { return p.id === id; })[0];
        return { label: part.label, unit: part.priceCents, quantity: s.centers };
      });
      if (s.otherAmountCents !== undefined) lines.push({ label: 'Other amount', unit: s.otherAmountCents, quantity: 1 });
      var total = lines.reduce(function (sum, l) { return sum + l.unit * l.quantity; }, 0);
      return { monthly: false, perPaymentCents: total, installments: 1, totalCents: total, lines: lines };
    }
    var m = s.frequency === 'monthly';
    return {
      monthly: m, perPaymentCents: s.amountCents, installments: m ? null : 1, totalCents: m ? null : s.amountCents,
      lines: [{ label: 'Donation', unit: s.amountCents, quantity: 1 }]
    };
  }

  /* ---------------------------------------------------------------- */
  /* Drawing                                                           */
  /* ---------------------------------------------------------------- */

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function render() {
    var opt = option();
    var sel = toSelection();
    var det = toDetails();
    var selErrors = validateSelection(sel);
    var quote = Object.keys(selErrors).length ? null : buildQuote(sel);

    // Panels and the step 2 heading
    $$('[data-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== opt; });
    var o = CFG.options[opt];
    $('#step-2-title').textContent = o.panelTitle;
    $('#step-2-sub').textContent = o.blurb;

    // Plan tiles show the price for the chosen number of centers
    var n = Number((centersInput() || {}).value);
    var count = isWhole(n) && n >= 1 ? n : 1;
    CFG.plans.forEach(function (p) {
      var t = $('[data-plan-price="' + p.id + '"]');
      if (t) t.textContent = money(p.perPaymentCents * count);
    });

    // Steppers
    $$('[data-stepper]').forEach(function (st) {
      var input = $('[data-centers]', st);
      var v = Number(input.value);
      var valid = isWhole(v) && v >= 1;
      $('[data-step="-1"]', st).disabled = valid && v <= 1;
      $('[data-step="1"]', st).disabled = valid && v >= CFG.maxCenters;
    });

    // Optional fields
    $('#center-name-wrap').hidden = !$('#field-wantsNaming').checked;
    var honor = det.honorType;
    $('#honor-name-wrap').hidden = honor === 'none';
    $('#field-honorName').required = honor !== 'none';
    $('#honor-label').textContent = honor === 'memory' ? 'Name of the person you are remembering' : 'Name of the person you are honoring';

    var isCenter = opt === 'center';
    $('#recognition-label').textContent = isCenter
      ? 'How should your name appear on the center plaque?'
      : 'How should your name appear in our recognition? (optional)';
    $('#recognition-required').hidden = !isCenter;
    $('#field-recognitionName').required = isCenter;
    $('#field-recognitionName-hint').textContent = isCenter
      ? 'Write it exactly as it should read, for example “The Khan Family”.'
      : 'Leave this blank if you would rather not be named.';

    // Preset chips
    var amt = dollarsToCents($('#field-amountCents').value);
    $$('[data-preset]').forEach(function (b) {
      b.setAttribute('aria-pressed', String(Number(b.getAttribute('data-preset')) === amt));
    });

    // Summary
    var total = $('#summary-total');
    total.textContent = '';
    var lines = $('#summary-lines');
    lines.textContent = '';
    if (quote) {
      var big = el('p', 'wef-total', money(quote.perPaymentCents));
      if (quote.monthly) big.appendChild(el('small', '', ' / month'));
      total.appendChild(big);
      total.appendChild(el('p', 'wef-summary-note',
        quote.monthly && quote.installments
          ? quote.installments + ' monthly payments, ' + money(quote.totalCents) + ' in all. Then it stops by itself.'
          : quote.monthly ? 'Every month until you ask us to stop.' : 'One-time gift.'));
      quote.lines.forEach(function (l) {
        var li = el('li');
        li.appendChild(el('span', '', (l.quantity > 1 ? l.quantity + ' × ' : '') + l.label));
        li.appendChild(el('span', '', money(l.unit * l.quantity)));
        lines.appendChild(li);
      });
    } else {
      total.appendChild(el('p', 'wef-summary-note', 'Make your choices and your total will appear here.'));
    }
    lines.hidden = !quote;

    // Errors, only once the donor has tried to continue
    var errors = attempted ? Object.assign({}, validateDonation(sel, det), serverErrors) : {};
    $$('[data-error]').forEach(function (p) {
      var key = p.getAttribute('data-error');
      var msg = errors[key] || '';
      // Steppers exist in two panels: only the visible one shows its error.
      var inHiddenPanel = p.closest('[data-panel]') && p.closest('[data-panel]').hidden;
      p.textContent = inHiddenPanel ? '' : msg;
      p.hidden = !msg || inHiddenPanel;
    });
    $$('[data-field]').forEach(function (f) {
      var key = f.getAttribute('data-field');
      var target = f.matches('input') ? f : $('input', f);
      if (target && target.type !== 'radio' && target.type !== 'checkbox') {
        if (errors[key]) target.setAttribute('aria-invalid', 'true');
        else target.removeAttribute('aria-invalid');
      }
    });
    var count2 = Object.keys(errors).length;
    var box = $('#summary-errors');
    box.hidden = !(attempted && count2 > 0);
    box.textContent = count2 === 1 ? 'Please check the highlighted field.' : 'Please check the ' + count2 + ' highlighted fields.';
  }

  function showServerMessage(msg) {
    var p = $('#summary-server');
    p.textContent = msg || '';
    p.hidden = !msg;
  }

  function setBusy(on) {
    busy = on;
    var b = $('#donate-submit');
    b.disabled = on;
    b.textContent = on ? 'Opening secure checkout…' : 'Continue to payment';
  }

  /** Focus the first field that needs attention. */
  function focusField(key) {
    var visible = $$('[data-field="' + key + '"]').filter(function (f) {
      return !(f.closest('[data-panel]') && f.closest('[data-panel]').hidden) && !f.closest('[hidden]');
    })[0];
    if (!visible) return;
    var target = visible.matches('input, fieldset') ? visible : $('input', visible);
    if (target) target.focus();
  }

  /* ---------------------------------------------------------------- */
  /* Events                                                            */
  /* ---------------------------------------------------------------- */

  form.addEventListener('input', function (e) {
    if (e.target.matches('[data-centers]')) {
      // Digits only, at most three; both panels share one number.
      var v = e.target.value.replace(/\D/g, '').slice(0, 3);
      $$('[data-centers]').forEach(function (i) { i.value = v; });
    }
    render();
  });
  form.addEventListener('change', render);

  form.addEventListener('click', function (e) {
    var step = e.target.closest('[data-step]');
    if (step) {
      var input = centersInput();
      var n = Number(input.value);
      var base = isWhole(n) && n >= 1 ? n : 1;
      var next = Math.min(CFG.maxCenters, Math.max(1, base + Number(step.getAttribute('data-step'))));
      $$('[data-centers]').forEach(function (i) { i.value = String(next); });
      render();
      return;
    }
    var preset = e.target.closest('[data-preset]');
    if (preset) {
      $('#field-amountCents').value = String(Number(preset.getAttribute('data-preset')) / 100);
      render();
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    attempted = true;
    serverErrors = {};
    showServerMessage('');

    var sel = toSelection();
    var det = toDetails();
    var errors = validateDonation(sel, det);
    render();
    if (Object.keys(errors).length) {
      focusField(Object.keys(errors)[0]);
      return;
    }

    // JSON.stringify turns a not-a-number into null, which the server rejects
    // with the right message, as it did with NaN in the original.
    setBusy(true);
    fetch(CFG.checkoutUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ selection: sel, details: det }),
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (res.ok && data.url) {
          window.location.assign(data.url); // off to Stripe; the button stays disabled
          return;
        }
        serverErrors = data.fieldErrors || {};
        showServerMessage(data.error || 'Something went wrong. Nothing was charged. Please try again.');
        setBusy(false);
        render();
      });
    }).catch(function () {
      showServerMessage('We could not reach the server. Nothing was charged. Please check your connection and try again.');
      setBusy(false);
    });
  });

  // Coming back with the browser's Back button from Stripe restores the page
  // from cache with the button still disabled.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) setBusy(false);
  });

  render();
})();
