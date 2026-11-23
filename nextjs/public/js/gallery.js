/* Gallery page: project filters and the Highlights slideshow.
   Loaded with defer on gallery.php only; the lightbox itself stays in
   main.js (initGalleryLightbox), which reads the same .gallery-item tiles. */
(function () {
  const tiles = Array.from(document.querySelectorAll('.gallery-grid .gallery-item'));
  if (!tiles.length) return;

  /* --- Highlights ---------------------------------------------------
     The photos currently shown, project by project in the page's order,
     crossfading between two
     stacked <img> layers. Follows the project filter: pick a project and
     the slideshow shows that project's photos. Pauses while pointed at or
     focused, and never advances on its own for visitors who ask for
     reduced motion (the arrows still work). */
  const box = document.getElementById('gallery-highlights');
  const INTERVAL = 4500;
  let order = [];
  let pos = 0;
  let timer = null;
  let paused = false;

  const slides = box ? Array.from(box.querySelectorAll('.gallery-highlights-slide')) : [];
  const projectEl = box ? box.querySelector('[data-highlight-project]') : null;
  const captionEl = box ? box.querySelector('[data-highlight-caption]') : null;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  function photoOf(tile) {
    const img = tile.querySelector('img');
    return {
      tile,
      src: img.getAttribute('src'),
      caption: img.dataset.caption || img.alt || '',
      project: tile.dataset.project || '',
    };
  }

  // The projects in the page's order (WEF at a Glance: Bright Beginnings
  // first), one photo from each in turn - so the slideshow opens on Bright
  // Beginnings, moves through the projects in that order, and the next
  // highlight is (when there is a choice) always from a different project.
  function mixByProject(list) {
    const groups = {};
    list.forEach((photo) => { (groups[photo.project] = groups[photo.project] || []).push(photo); });
    const queues = Object.values(groups).map((g) => g.slice());
    const out = [];
    while (queues.some((q) => q.length)) {
      queues.forEach((q) => { if (q.length) out.push(q.shift()); });
    }
    return out;
  }

  function show(photo) {
    if (!box || !photo) return;
    const current = slides.find((s) => s.classList.contains('is-active')) || slides[0];
    const next = slides.find((s) => s !== current) || current;
    next.onload = () => {
      next.alt = photo.caption;
      next.removeAttribute('aria-hidden');
      current.setAttribute('aria-hidden', 'true');
      current.alt = '';
      next.classList.add('is-active');
      if (next !== current) current.classList.remove('is-active');
    };
    next.src = photo.src;
    if (next.complete && next.naturalWidth) next.onload();
    if (projectEl) projectEl.textContent = photo.project;
    if (captionEl) captionEl.textContent = photo.caption !== photo.project ? photo.caption : '';
  }

  function rebuild() {
    order = mixByProject(tiles.filter((t) => !t.hidden).map(photoOf));
    pos = 0;
    show(order[0]);
    restart();
  }

  function step(delta) {
    if (!order.length) return;
    pos = (pos + delta + order.length) % order.length;
    show(order[pos]);
  }

  function restart() {
    clearInterval(timer);
    timer = null;
    if (!box || paused || reduceMotion.matches || order.length < 2) return;
    timer = setInterval(() => step(1), INTERVAL);
  }

  if (box) {
    // Photos load one at a time as the slideshow reaches them; the first
    // one is already in the markup.
    order = mixByProject(tiles.map(photoOf));
    const first = slides[0].getAttribute('src');
    const at = order.findIndex((p) => p.src === first);
    if (at > 0) order = order.slice(at).concat(order.slice(0, at));

    box.querySelector('[data-highlight-prev]').addEventListener('click', (e) => { e.stopPropagation(); step(-1); restart(); });
    box.querySelector('[data-highlight-next]').addEventListener('click', (e) => { e.stopPropagation(); step(1); restart(); });

    // Clicking the photo opens it in the lightbox, through its grid tile.
    box.addEventListener('click', () => {
      const photo = order[pos];
      if (photo) photo.tile.click();
    });

    const pause = () => { paused = true; restart(); };
    const resume = () => { paused = false; restart(); };
    box.addEventListener('mouseenter', pause);
    box.addEventListener('mouseleave', resume);
    box.addEventListener('focusin', pause);
    box.addEventListener('focusout', resume);
    document.addEventListener('visibilitychange', () => (document.hidden ? pause() : resume()));
    reduceMotion.addEventListener('change', restart);

    restart();
  }

  /* --- Project filters ---------------------------------------------- */
  const filters = Array.from(document.querySelectorAll('[data-gallery-filter]'));
  filters.forEach((button) => {
    button.addEventListener('click', () => {
      const project = button.dataset.galleryFilter;
      filters.forEach((b) => {
        const on = b === button;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      tiles.forEach((tile) => {
        tile.hidden = project !== '' && tile.dataset.project !== project;
      });
      if (box) rebuild();
    });
  });
})();
