/**
 * Admin panel behaviour.
 *
 * This is the first JavaScript in the admin app — before it, every form
 * was double-submittable, file inputs gave no feedback until after a
 * round trip, and destructive actions relied on the browser's native
 * confirm() dialog.
 *
 * Everything here is progressive enhancement: with JS disabled, forms
 * still submit, uploads are still validated server-side by
 * includes/upload.php, and the delete confirmations fall back to the
 * inline onsubmit="return confirm(...)" already on those forms.
 */
(function () {
  'use strict';

  /* ---------------------------------------------------------------
   * Dark Mode Toggle
   * ------------------------------------------------------------- */
  function initThemeToggle() {
    var toggle = document.getElementById('theme-toggle');
    if (!toggle) return;
    
    var sun = toggle.querySelector('.sun-icon');
    var moon = toggle.querySelector('.moon-icon');
    
    function updateIcons(isDark) {
      if (isDark) {
        sun.style.display = 'none';
        moon.style.display = 'block';
      } else {
        sun.style.display = 'block';
        moon.style.display = 'none';
      }
    }
    
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    updateIcons(isDark);
    
    toggle.addEventListener('click', function() {
      isDark = !isDark;
      if (isDark) {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('admin-theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
        localStorage.removeItem('admin-theme');
      }
      updateIcons(isDark);
    });
  }

  /* ---------------------------------------------------------------
   * Mobile navigation
   * ------------------------------------------------------------- */
  function initNav() {
    var shell = document.querySelector('.app-shell');
    var toggle = document.querySelector('[data-nav-toggle]');
    var backdrop = document.querySelector('[data-nav-close]');
    if (!shell || !toggle) return;

    function setOpen(open) {
      shell.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      if (backdrop) backdrop.hidden = !open;
    }

    toggle.addEventListener('click', function () {
      setOpen(!shell.classList.contains('nav-open'));
    });
    if (backdrop) backdrop.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && shell.classList.contains('nav-open')) setOpen(false);
    });
  }

  /* ---------------------------------------------------------------
   * Filter selects submit their form on change
   * ------------------------------------------------------------- */
  function initAutoSubmit() {
    document.querySelectorAll('[data-list-autosubmit]').forEach(function (select) {
      select.addEventListener('change', function () {
        if (select.form) select.form.submit();
      });
    });
  }

  /* ---------------------------------------------------------------
   * Image upload: preview + client-side validation
   *
   * The limits here mirror includes/upload.php exactly. This is a
   * courtesy check so the user finds out before uploading 20MB, not a
   * security boundary — the server re-checks with real MIME sniffing.
   * ------------------------------------------------------------- */
  var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  var IMAGE_MAX_BYTES = 5 * 1024 * 1024;
  var PDF_MAX_BYTES = 15 * 1024 * 1024;

  function formatBytes(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
  }

  function fieldError(input, message) {
    var holder = input.closest('.field') || input.parentNode;
    var existing = holder.querySelector('.field-error');

    if (!message) {
      if (existing) existing.remove();
      input.removeAttribute('aria-invalid');
      return;
    }

    if (!existing) {
      existing = document.createElement('p');
      existing.className = 'field-error';
      existing.setAttribute('role', 'alert');
      holder.appendChild(existing);
    }
    existing.textContent = message;
    input.setAttribute('aria-invalid', 'true');
  }

  function initFileInputs() {
    document.querySelectorAll('input[type="file"]').forEach(function (input) {
      var isPdf = input.getAttribute('accept') === '.pdf' || input.name === 'pdf';
      var maxBytes = isPdf ? PDF_MAX_BYTES : IMAGE_MAX_BYTES;

      var preview = document.createElement('div');
      preview.className = 'upload-preview';
      preview.hidden = true;
      input.parentNode.insertBefore(preview, input.nextSibling);

      input.addEventListener('change', function () {
        fieldError(input, '');
        preview.hidden = true;
        preview.innerHTML = '';

        var file = input.files && input.files[0];
        if (!file) return;

        if (file.size > maxBytes) {
          fieldError(input, 'That file is ' + formatBytes(file.size) + ' — the limit is ' + formatBytes(maxBytes) + '.');
          input.value = '';
          return;
        }
        if (!isPdf && IMAGE_TYPES.indexOf(file.type) === -1) {
          fieldError(input, 'Please choose a JPEG, PNG, or WebP image.');
          input.value = '';
          return;
        }

        var meta = document.createElement('span');
        meta.className = 'upload-preview-meta';
        meta.textContent = file.name + ' — ' + formatBytes(file.size);

        if (!isPdf) {
          var img = document.createElement('img');
          img.alt = 'Preview of the image about to be uploaded';
          img.src = URL.createObjectURL(file);
          img.addEventListener('load', function () { URL.revokeObjectURL(img.src); });
          preview.appendChild(img);
        }

        preview.appendChild(meta);
        preview.hidden = false;
      });
    });
  }

  /* ---------------------------------------------------------------
   * "Remove current image" checkbox
   *
   * Ticking it dims the existing image so it's obvious the file is
   * going away when the form is saved.
   * ------------------------------------------------------------- */
  function initRemoveToggles() {
    document.querySelectorAll('[data-remove-toggle]').forEach(function (checkbox) {
      var target = document.getElementById(checkbox.getAttribute('data-remove-toggle'));
      if (!target) return;
      checkbox.addEventListener('change', function () {
        target.classList.toggle('is-marked-for-removal', checkbox.checked);
      });
    });
  }

  /* ---------------------------------------------------------------
   * Loading state + double-submit protection
   *
   * Submitting twice previously meant two POSTs: on a create form that
   * surfaced as a confusing "slug already in use" error, and on a form
   * with a file it uploaded the file twice.
   * ------------------------------------------------------------- */
  function initSubmitGuards() {
    // Registered once, not per submit: if the browser restores this page
    // from bfcache (back button), every guarded form must become usable
    // again rather than staying stuck in its disabled loading state.
    window.addEventListener('pageshow', function () {
      document.querySelectorAll('form[data-submitting="true"]').forEach(function (form) {
        form.dataset.submitting = 'false';
        var button = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!button) return;
        button.classList.remove('is-loading');
        button.removeAttribute('aria-busy');
        if (button.dataset.originalText) button.textContent = button.dataset.originalText;
      });
    });

    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        // Let an onsubmit="return confirm(...)" veto win.
        if (event.defaultPrevented) return;

        if (form.dataset.submitting === 'true') {
          event.preventDefault();
          return;
        }
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
          return; // browser will show its own validation UI
        }

        form.dataset.submitting = 'true';

        var button = form.querySelector('button[type="submit"], input[type="submit"]');
        if (button) {
          // Deliberately NOT button.disabled = true. Disabling the submit
          // button from inside its own submit handler cancels the
          // submission in some browsers — which turned this guard into
          // the reason Delete and Save did nothing. aria-busy plus the
          // is-loading class give the same visual feedback, and the
          // dataset.submitting flag above is what actually prevents the
          // second submit.
          button.classList.add('is-loading');
          button.setAttribute('aria-busy', 'true');
          if (button.tagName === 'BUTTON') {
            button.dataset.originalText = button.textContent;
            button.textContent = button.dataset.loadingText || 'Working…';
          }
        }
      });
    });
  }

  /* ---------------------------------------------------------------
   * Confirmation dialogs for destructive actions
   *
   * Replaces window.confirm() with an in-page dialog that names what is
   * being deleted. The original onsubmit attribute is removed only once
   * this has successfully taken over, so a failure here leaves the
   * native confirm in place rather than removing the guard entirely.
   * ------------------------------------------------------------- */
  function initConfirmDialogs() {
    var dialog = document.createElement('div');
    dialog.className = 'confirm-backdrop';
    dialog.hidden = true;
    dialog.innerHTML =
      '<div class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">' +
        '<h2 id="confirm-title">Are you sure?</h2>' +
        '<p data-confirm-message></p>' +
        '<div class="confirm-actions">' +
          '<button type="button" class="btn-filter" data-confirm-cancel>Cancel</button>' +
          '<button type="button" class="btn-danger" data-confirm-ok>Delete</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(dialog);

    var messageEl = dialog.querySelector('[data-confirm-message]');
    var okButton = dialog.querySelector('[data-confirm-ok]');
    var cancelButton = dialog.querySelector('[data-confirm-cancel]');
    var pendingForm = null;
    var lastFocused = null;

    function close() {
      dialog.hidden = true;
      pendingForm = null;
      if (lastFocused) lastFocused.focus();
    }

    cancelButton.addEventListener('click', close);
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) close();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !dialog.hidden) close();
    });

    okButton.addEventListener('click', function () {
      var form = pendingForm;
      close();
      if (!form) return;
      form.dataset.confirmed = 'true';
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });

    // Declarative: the message comes from a data-confirm attribute the
    // PHP renders. The previous version read the inline onsubmit="return
    // confirm('…')" attribute and pulled the message out with a regex,
    // which was brittle in exactly the wrong place — a content title
    // containing an apostrophe (e.g. "Teachers' Training Program")
    // produces an onsubmit attribute that is itself invalid JavaScript.
    // Nothing is parsed out of an attribute here, and no attribute is
    // removed, so there is no state where the guard is half-installed.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (form.dataset.confirmed === 'true') return;
        event.preventDefault();
        event.stopImmediatePropagation();
        lastFocused = document.activeElement;
        pendingForm = form;
        messageEl.textContent = form.getAttribute('data-confirm');
        dialog.hidden = false;
        okButton.focus();
      });
    });
  }

  /* ---------------------------------------------------------------
   * Bulk selection
   *
   * The checkboxes live inside the table but belong to a <form
   * id="bulk-form"> that sits outside it, via the HTML5 form attribute —
   * a form can't be nested inside the table alongside the per-row delete
   * forms. The bar only appears once something is selected.
   * ------------------------------------------------------------- */
  function initBulk() {
    var bar = document.querySelector('[data-bulk-bar]');
    var selectAll = document.querySelector('[data-bulk-all]');
    var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-item]'));
    if (!bar || boxes.length === 0) return;

    var counter = bar.querySelector('[data-bulk-count]');

    function sync() {
      var checked = boxes.filter(function (b) { return b.checked; });
      bar.hidden = checked.length === 0;
      if (counter) {
        counter.textContent = checked.length + ' selected';
      }
      if (selectAll) {
        selectAll.checked = checked.length === boxes.length && boxes.length > 0;
        selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
      }
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        boxes.forEach(function (b) { b.checked = selectAll.checked; });
        sync();
      });
    }
    boxes.forEach(function (b) { b.addEventListener('change', sync); });

    var form = document.getElementById('bulk-form');
    if (form) {
      form.addEventListener('submit', function (event) {
        var select = document.querySelector('select[name="bulk_action"]');
        var count = boxes.filter(function (b) { return b.checked; }).length;
        if (!select || !select.value || count === 0) {
          event.preventDefault();
          return;
        }
        if (select.value === 'delete') {
          var ok = window.confirm('Permanently delete ' + count + ' item(s)? This cannot be undone.');
          if (!ok) event.preventDefault();
        }
      });
    }

    sync();
  }

  /* ---------------------------------------------------------------
   * Character counters
   *
   * Generic: any input or textarea that declares maxlength gets a live
   * "123 / 160" readout, so limits are visible while typing instead of
   * being discovered when the browser silently stops accepting keystrokes.
   * Nothing per-field to maintain — adding maxlength in the PHP is enough.
   * ------------------------------------------------------------- */
  function initCharCounters() {
    document.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(function (field) {
      var max = parseInt(field.getAttribute('maxlength'), 10);
      if (!max || max < 20) return; // skip tiny fields where a counter is noise

      var counter = document.createElement('span');
      counter.className = 'char-counter';
      // Announced only when it matters (see the warn/over classes below),
      // otherwise a screen reader would read a number on every keystroke.
      counter.setAttribute('aria-live', 'off');

      var holder = field.closest('.field') || field.parentNode;
      holder.appendChild(counter);

      function update() {
        var used = field.value.length;
        counter.textContent = used + ' / ' + max;
        counter.classList.toggle('is-warn', used >= max * 0.9 && used < max);
        counter.classList.toggle('is-full', used >= max);
      }

      field.addEventListener('input', update);
      update();
    });
  }

  /* ---------------------------------------------------------------
   * Image cropper
   *
   * Opens after a file is chosen on any input carrying data-crop-width
   * and data-crop-height (set from includes/media-specs.php). The crop
   * box is locked to that aspect ratio; the user pans and zooms the
   * image behind it. On confirm the visible region is drawn to a canvas
   * at the exact target pixel size and swapped back into the file input
   * via DataTransfer, so the form submits the cropped file and the
   * server sees an ordinary upload — no new endpoint, no change to
   * includes/upload.php.
   *
   * This also shrinks uploads a lot: a 4MB phone photo leaves here as a
   * ~200KB 1200x800 JPEG.
   * ------------------------------------------------------------- */
  function initCropper() {
    var inputs = document.querySelectorAll('input[type="file"][data-crop-width]');
    if (inputs.length === 0 || typeof DataTransfer === 'undefined') return;

    var ui = document.createElement('div');
    ui.className = 'crop-backdrop';
    ui.hidden = true;
    ui.innerHTML =
      '<div class="crop-dialog" role="dialog" aria-modal="true" aria-labelledby="crop-title">' +
        '<h2 id="crop-title">Crop image</h2>' +
        '<p class="crop-hint" data-crop-hint></p>' +
        '<div class="crop-stage" data-crop-stage>' +
          '<canvas data-crop-canvas></canvas>' +
          '<div class="crop-frame" data-crop-frame></div>' +
        '</div>' +
        '<label class="crop-zoom">' +
          '<span>Zoom</span>' +
          '<input type="range" min="1" max="4" step="0.01" value="1" data-crop-zoom>' +
        '</label>' +
        '<div class="crop-actions">' +
          '<button type="button" class="btn-filter" data-crop-cancel>Cancel</button>' +
          '<button type="button" class="btn-filter" data-crop-skip>Use original</button>' +
          '<button type="button" class="btn-primary" data-crop-apply>Apply crop</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(ui);

    var canvas = ui.querySelector('[data-crop-canvas]');
    var ctx = canvas.getContext('2d');
    var zoomEl = ui.querySelector('[data-crop-zoom]');
    var hintEl = ui.querySelector('[data-crop-hint]');
    var frameEl = ui.querySelector('[data-crop-frame]');

    var state = null; // { input, image, targetW, targetH, zoom, offsetX, offsetY, viewW, viewH }

    function close() {
      ui.hidden = true;
      if (state && state.objectUrl) URL.revokeObjectURL(state.objectUrl);
      state = null;
    }

    // Keeps the image covering the crop frame at all times, so a crop can
    // never include blank space outside the photo.
    function clampOffsets() {
      var drawW = state.baseW * state.zoom;
      var drawH = state.baseH * state.zoom;
      var minX = state.viewW - drawW;
      var minY = state.viewH - drawH;
      state.offsetX = Math.min(0, Math.max(minX, state.offsetX));
      state.offsetY = Math.min(0, Math.max(minY, state.offsetY));
    }

    function render() {
      if (!state) return;
      clampOffsets();
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(
        state.image,
        state.offsetX, state.offsetY,
        state.baseW * state.zoom, state.baseH * state.zoom
      );
    }

    function open(input, file) {
      var targetW = parseInt(input.getAttribute('data-crop-width'), 10);
      var targetH = parseInt(input.getAttribute('data-crop-height'), 10);
      var objectUrl = URL.createObjectURL(file);
      var image = new Image();

      image.onload = function () {
        // The on-screen stage matches the target aspect ratio, capped so
        // the dialog fits comfortably on a laptop screen.
        var viewW = Math.min(520, window.innerWidth - 90);
        var viewH = Math.round(viewW * (targetH / targetW));
        var maxH = window.innerHeight - 300;
        if (viewH > maxH) {
          viewH = Math.max(180, maxH);
          viewW = Math.round(viewH * (targetW / targetH));
        }

        canvas.width = viewW;
        canvas.height = viewH;
        frameEl.style.width = viewW + 'px';
        frameEl.style.height = viewH + 'px';

        // "cover" scale: smallest zoom that still fills the frame.
        var scale = Math.max(viewW / image.width, viewH / image.height);

        state = {
          input: input,
          file: file,
          image: image,
          objectUrl: objectUrl,
          targetW: targetW,
          targetH: targetH,
          viewW: viewW,
          viewH: viewH,
          baseW: image.width * scale,
          baseH: image.height * scale,
          zoom: 1,
          offsetX: 0,
          offsetY: 0
        };
        state.offsetX = (viewW - state.baseW) / 2;
        state.offsetY = (viewH - state.baseH) / 2;

        zoomEl.value = '1';
        hintEl.textContent =
          (input.getAttribute('data-crop-label') || 'Image') +
          ' — will be saved at ' + targetW + ' × ' + targetH + ' px. Drag to reposition, zoom to fill.';

        ui.hidden = false;
        render();
      };

      image.onerror = function () {
        URL.revokeObjectURL(objectUrl);
        // Not fatal: leave the original file in place and let the server
        // validate it as usual.
      };

      image.src = objectUrl;
    }

    // --- pan ---
    var dragging = false;
    var lastX = 0;
    var lastY = 0;

    function pointerDown(event) {
      if (!state) return;
      dragging = true;
      var point = event.touches ? event.touches[0] : event;
      lastX = point.clientX;
      lastY = point.clientY;
      if (!event.touches) event.preventDefault();
    }

    function pointerMove(event) {
      if (!dragging || !state) return;
      var point = event.touches ? event.touches[0] : event;
      state.offsetX += point.clientX - lastX;
      state.offsetY += point.clientY - lastY;
      lastX = point.clientX;
      lastY = point.clientY;
      render();
      if (event.touches) event.preventDefault();
    }

    function pointerUp() { dragging = false; }

    canvas.addEventListener('mousedown', pointerDown);
    window.addEventListener('mousemove', pointerMove);
    window.addEventListener('mouseup', pointerUp);
    canvas.addEventListener('touchstart', pointerDown, { passive: true });
    canvas.addEventListener('touchmove', pointerMove, { passive: false });
    canvas.addEventListener('touchend', pointerUp);

    zoomEl.addEventListener('input', function () {
      if (!state) return;
      var previous = state.zoom;
      state.zoom = parseFloat(zoomEl.value);
      // Zoom about the centre of the frame rather than the top-left, so
      // the subject stays put instead of drifting away.
      var factor = state.zoom / previous;
      state.offsetX = state.viewW / 2 - (state.viewW / 2 - state.offsetX) * factor;
      state.offsetY = state.viewH / 2 - (state.viewH / 2 - state.offsetY) * factor;
      render();
    });

    ui.querySelector('[data-crop-cancel]').addEventListener('click', function () {
      if (state) state.input.value = ''; // cancel = no file chosen
      close();
    });

    // Escape hatch: keep the file exactly as picked, skipping the crop.
    ui.querySelector('[data-crop-skip]').addEventListener('click', close);

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !ui.hidden) close();
    });

    ui.querySelector('[data-crop-apply]').addEventListener('click', function () {
      if (!state) return;

      var out = document.createElement('canvas');
      out.width = state.targetW;
      out.height = state.targetH;
      var outCtx = out.getContext('2d');
      outCtx.imageSmoothingQuality = 'high';

      // Map the on-screen view onto the full-size target canvas.
      var ratio = state.targetW / state.viewW;
      outCtx.drawImage(
        state.image,
        state.offsetX * ratio,
        state.offsetY * ratio,
        state.baseW * state.zoom * ratio,
        state.baseH * state.zoom * ratio
      );

      var input = state.input;
      var originalName = state.file.name.replace(/\.[^.]+$/, '');

      out.toBlob(function (blob) {
        if (!blob) { close(); return; }
        var cropped = new File([blob], originalName + '.jpg', { type: 'image/jpeg' });
        var transfer = new DataTransfer();
        transfer.items.add(cropped);
        input.files = transfer.files;
        // Mark this exact file as already-cropped BEFORE re-dispatching,
        // otherwise the change listener below reopens the cropper on the
        // cropper's own output, in a loop.
        input.dataset.cropDone = cropped.name + cropped.size;
        close();
        // Re-run the preview/validation listeners against the new file.
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }, 'image/jpeg', 0.88);
    });

    inputs.forEach(function (input) {
      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        // Only offer cropping for images we just picked, and skip files
        // the cropper produced itself (avoids reopening on the re-dispatch).
        if (!file || file.type.indexOf('image/') !== 0) return;
        if (input.dataset.cropDone === file.name + file.size) return;
        open(input, file);
      });
    });
  }

  /* ---------------------------------------------------------------
   * Drag-to-reorder
   *
   * Rows in a table marked data-reorder-url become draggable by their
   * handle. On drop the whole visible order is POSTed at once (not a
   * pairwise swap), so the server writes one consistent sequence and a
   * dropped request can't leave two items sharing a position.
   *
   * Uses native HTML5 drag events, which give keyboard-independent
   * behaviour for free in every target browser here; the up/down buttons
   * below cover keyboard users, who can't drag at all.
   * ------------------------------------------------------------- */
  function initReorder() {
    var table = document.querySelector('table[data-reorder-url]');
    if (!table) return;

    var tbody = table.querySelector('tbody');
    var url = table.getAttribute('data-reorder-url');
    var token = table.getAttribute('data-reorder-token');
    var status = document.querySelector('[data-reorder-status]');
    var dragging = null;

    function announce(text, isError) {
      if (!status) return;
      status.textContent = text;
      status.classList.toggle('is-error', !!isError);
      if (!isError) {
        window.setTimeout(function () {
          if (status.textContent === text) status.textContent = '';
        }, 2500);
      }
    }

    function persist() {
      var ids = Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'), function (row) {
        return row.getAttribute('data-id');
      });

      var body = new FormData();
      body.append('csrf_token', token);
      ids.forEach(function (id) { body.append('ids[]', id); });

      announce('Saving order…');

      fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
        .then(function (r) {
          announce(r.ok ? 'Order saved.' : (r.data.error || 'Could not save the new order.'), !r.ok);
        })
        .catch(function () {
          announce('Could not save the new order — check your connection and reload.', true);
        });
    }

    tbody.addEventListener('dragstart', function (event) {
      var row = event.target.closest('tr[data-id]');
      if (!row) return;
      dragging = row;
      row.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      // Firefox refuses to start a drag unless some data is set.
      event.dataTransfer.setData('text/plain', row.getAttribute('data-id'));
    });

    tbody.addEventListener('dragover', function (event) {
      if (!dragging) return;
      event.preventDefault();
      var over = event.target.closest('tr[data-id]');
      if (!over || over === dragging) return;

      var box = over.getBoundingClientRect();
      var below = (event.clientY - box.top) > box.height / 2;
      tbody.insertBefore(dragging, below ? over.nextSibling : over);
    });

    tbody.addEventListener('drop', function (event) { event.preventDefault(); });

    tbody.addEventListener('dragend', function () {
      if (!dragging) return;
      dragging.classList.remove('is-dragging');
      dragging = null;
      persist();
    });

    // Keyboard equivalent — dragging is unusable without a mouse.
    tbody.addEventListener('click', function (event) {
      var button = event.target.closest('[data-move]');
      if (!button) return;
      var row = button.closest('tr[data-id]');
      var target = button.getAttribute('data-move') === 'up'
        ? row.previousElementSibling
        : row.nextElementSibling;
      if (!target) return;

      if (button.getAttribute('data-move') === 'up') {
        tbody.insertBefore(row, target);
      } else {
        tbody.insertBefore(target, row);
      }
      button.focus();
      persist();
    });
  }

  /* ---------------------------------------------------------------
   * List / grid view toggle
   *
   * Purely a display preference, remembered per module in localStorage
   * (mirrors the dark-mode toggle above) — nothing is sent to the server,
   * so a cleared browser or a private window just starts back on List.
   * The two panels this switches between both already exist in the page
   * (see viewToggle() in admin/includes/listing.php); this only flips
   * which one is visible.
   * ------------------------------------------------------------- */
  function initViewToggle() {
    document.querySelectorAll('[data-view-toggle]').forEach(function (toggle) {
      var key = toggle.getAttribute('data-view-toggle');
      var panel = document.querySelector('.listing-view[data-view-key="' + key + '"]');
      if (!panel) return;

      var storageKey = 'admin-view-' + key;
      var buttons = toggle.querySelectorAll('[data-view-set]');

      function apply(view) {
        panel.setAttribute('data-active-view', view);
        buttons.forEach(function (b) {
          b.setAttribute('aria-pressed', String(b.getAttribute('data-view-set') === view));
        });
      }

      var stored = null;
      try { stored = localStorage.getItem(storageKey); } catch (e) { /* private window, storage blocked */ }
      apply(stored === 'grid' ? 'grid' : 'list');

      buttons.forEach(function (button) {
        button.addEventListener('click', function () {
          var view = button.getAttribute('data-view-set');
          apply(view);
          try { localStorage.setItem(storageKey, view); } catch (e) { /* not persisted this session */ }
        });
      });
    });
  }

  /* ---------------------------------------------------------------
   * Copy link (Media Manager): the file's full web address, ready to
   * paste into a "Read more" link or an email. The button carries the
   * relative URL; the browser resolves it against this page.
   * ------------------------------------------------------------- */
  function initCopyLinks() {
    document.querySelectorAll('[data-copy-url]').forEach(function (button) {
      button.addEventListener('click', function () {
        var url = new URL(button.getAttribute('data-copy-url'), window.location.href).href;
        var label = button.textContent;
        var done = function (text) {
          button.textContent = text;
          setTimeout(function () { button.textContent = label; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(url).then(function () { done('Copied!'); }, function () { window.prompt('Copy this link:', url); });
        } else {
          // Plain-http localhost has no clipboard API.
          window.prompt('Copy this link:', url);
        }
      });
    });
  }

  function init() {
    initCopyLinks();
    initThemeToggle();
    initNav();
    initBulk();
    initReorder();
    initViewToggle();
    initCharCounters();
    initAutoSubmit();
    // Cropper before initFileInputs so its change listener runs first and
    // the preview below renders the cropped result, not the original pick.
    initCropper();
    initFileInputs();
    initRemoveToggles();
    initConfirmDialogs();
    initSubmitGuards(); // last, so the confirm handler runs before it
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
