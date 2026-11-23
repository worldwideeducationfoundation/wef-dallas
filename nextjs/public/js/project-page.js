/* Project pages (includes/layout/project-page.php): the count-up numbers in
   the impact line and the photo carousel.
   Loaded with defer on the nine project pages only. Everything degrades to
   plain content without it: the final numbers and a stack of photos are
   all in the markup. */
(function () {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* --- Count-up numbers -----------------------------------------------
     "3,000" counts from 0 when the band scrolls into view; a prefix or
     suffix ("100+", "$120,000") is kept as written. */
  const counters = document.querySelectorAll('[data-count-up]');
  if (counters.length && !reduceMotion && 'IntersectionObserver' in window) {
    const run = (el) => {
      const text = el.textContent.trim();
      const match = /^(\D*)([\d,]+(?:\.\d+)?)(.*)$/.exec(text);
      if (!match) return;
      const [, prefix, number, suffix] = match;
      const target = parseFloat(number.replace(/,/g, ''));
      const decimals = (number.split('.')[1] || '').length;
      const useCommas = number.includes(',');
      const start = performance.now();
      const duration = 1400;
      const format = (v) => {
        const fixed = v.toFixed(decimals);
        return useCommas ? Number(fixed).toLocaleString('en-US', { minimumFractionDigits: decimals }) : fixed;
      };
      const frame = (now) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        el.textContent = prefix + format(target * eased) + suffix;
        if (t < 1) requestAnimationFrame(frame);
        else el.textContent = text;
      };
      requestAnimationFrame(frame);
    };
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        observer.unobserve(entry.target);
        run(entry.target);
      });
    }, { threshold: 0.5 });
    counters.forEach((el) => observer.observe(el));
  }

  /* --- Photo carousel ---------------------------------------------------
     The centre photo full size, its neighbours smaller and turned away,
     further ones hidden behind. Arrows, dots, swipe, click a side photo,
     Left/Right keys; advances on its own every 5s unless the visitor is
     interacting or asked for reduced motion. Every 4s. */
  document.querySelectorAll('[data-coverflow]').forEach((root) => {
    const slides = Array.from(root.querySelectorAll('.pp-coverflow-slide'));
    const dots = Array.from(root.querySelectorAll('[data-coverflow-dot]'));
    if (!slides.length) return;
    let active = 0;
    let timer = null;

    const layout = () => {
      const n = slides.length;
      const phone = window.matchMedia('(max-width: 768px)').matches;
      slides.forEach((slide, i) => {
        // Shortest signed distance around the loop, so the last photo sits
        // to the left of the first.
        let offset = i - active;
        if (offset > n / 2) offset -= n;
        if (offset < -n / 2) offset += n;
        const distance = Math.abs(offset);
        const shift = phone ? 62 : 72;
        const visible = distance <= 2;
        slide.style.transform = 'translateX(' + (offset * shift) + '%) scale(' + (1 - Math.min(distance, 2) * 0.16) + ') rotateY(' + (offset === 0 ? 0 : (offset > 0 ? -28 : 28)) + 'deg)';
        slide.style.zIndex = String(50 - distance);
        slide.style.opacity = visible ? String(1 - distance * 0.22) : '0';
        slide.style.filter = distance === 0 ? 'none' : 'saturate(0.85) brightness(0.92)';
        slide.style.pointerEvents = visible ? '' : 'none';
        slide.classList.toggle('is-active', distance === 0);
        slide.setAttribute('aria-hidden', distance === 0 ? 'false' : 'true');
      });
      dots.forEach((dot, i) => {
        dot.classList.toggle('is-active', i === active);
        dot.setAttribute('aria-current', i === active ? 'true' : 'false');
      });
    };

    const go = (i) => {
      active = (i + slides.length) % slides.length;
      layout();
    };

    const stop = () => { clearInterval(timer); timer = null; };
    const start = () => {
      stop();
      if (!reduceMotion && slides.length > 1) timer = setInterval(() => go(active + 1), 4000);
    };

    const prev = root.querySelector('[data-coverflow-prev]');
    const next = root.querySelector('[data-coverflow-next]');
    if (prev) prev.addEventListener('click', () => { go(active - 1); start(); });
    if (next) next.addEventListener('click', () => { go(active + 1); start(); });
    dots.forEach((dot) => dot.addEventListener('click', () => { go(Number(dot.dataset.coverflowDot)); start(); }));
    slides.forEach((slide, i) => slide.addEventListener('click', () => {
      if (swiped) { swiped = false; return; }
      if (i !== active) { go(i); start(); }
    }));

    root.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') { go(active - 1); start(); }
      if (e.key === 'ArrowRight') { go(active + 1); start(); }
    });

    // Swipe: a horizontal drag of 40px or more moves one photo.
    let startX = null;
    let swiped = false;
    const stage = root.querySelector('.pp-coverflow-stage');
    stage.addEventListener('pointerdown', (e) => { startX = e.clientX; });
    stage.addEventListener('pointerup', (e) => {
      if (startX === null) return;
      const dx = e.clientX - startX;
      startX = null;
      swiped = Math.abs(dx) >= 40;
      if (swiped) { go(active + (dx < 0 ? 1 : -1)); start(); }
    });
    stage.addEventListener('pointercancel', () => { startX = null; });

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('focusin', stop);
    // Clicking an arrow focuses it; without this the slideshow stayed
    // stopped for good after the first click.
    root.addEventListener('focusout', (e) => { if (!root.contains(e.relatedTarget)) start(); });
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
    window.addEventListener('resize', layout);

    layout();
    start();
  });
})();
