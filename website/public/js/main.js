/* ============================================
   Worldwide Education Foundation — Main JS
   Slideshow, Scroll Animations, Counter, Lightbox
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {
  // Each init runs independently: styles.css/impact-goals-new.css hide
  // .reveal, .text-animate-char and the impact-goals hero/summary/CTA by
  // default, with a separate html:not(.js) fallback (see styles.css)
  // that forces them visible whenever the inline <head> script never
  // got to add the .js class - JS disabled, blocked, or an earlier
  // init() throwing before it runs. Without this per-call try/catch, an
  // uncaught error in, say, initHeader() would stop every function after
  // it in this list from running at all - including initScrollReveal()
  // and initCharRevealHeadings(), the two that actually reveal that
  // content when JS *is* running. This is what makes the reveal happen
  // reliably even when some other init() misbehaves.
  const inits = [
    initImageSkeletons,
    initHeader,
    initSlideshow,
    initScrollReveal,
    initCounterAnimation,
    initMobileMenu,
    initGalleryLightbox,
    initSmoothScroll,
    initContactForm,
    initDonationTabs,
    initActiveNav,
    initNewsletterForm,
    initCompareSlider,
    initCharRevealHeadings,
    initImpactGoalsChart,
    initCardCarousels,
    initHonorBoardExpand,
    initHonorBoardMapLink,
    initProjectsLoop,
    initOpenUrlButtons,
    initProgramFrameFallback,
  ];
  inits.forEach((fn) => {
    try {
      fn();
    } catch (err) {
      console.error(fn.name + '() failed:', err);
    }
  });
});

/* --- Skeleton while a photo loads ---
   Every <img> still loading (including lazy ones further down the page)
   gets .is-loading: a soft shimmering placeholder in its place
   (styles.css, "Skeleton loading"). It comes off when the photo arrives -
   or fails, so a broken image never shimmers forever. One listener on the
   document catches every image's load/error, including photos whose src
   is set later (the slideshows). */
function initImageSkeletons() {
  const done = (e) => {
    if (e.target instanceof HTMLImageElement) e.target.classList.remove('is-loading');
  };
  document.addEventListener('load', done, true);
  document.addEventListener('error', done, true);

  document.querySelectorAll('img').forEach((img) => {
    if (!img.complete && img.getAttribute('src')) img.classList.add('is-loading');
  });
}

/* --- Buttons that open a page in a new tab ---
   The hero's Donate Now is a <button> styled as .button, and opened the
   donation page with an inline onclick. The Content-Security-Policy no longer
   runs inline handlers, so the URL rides on data-open-url instead. */
function initOpenUrlButtons() {
  document.querySelectorAll('[data-open-url]').forEach((btn) => {
    btn.addEventListener('click', () => {
      window.open(btn.getAttribute('data-open-url'), '_blank', 'noopener');
    });
  });
}

/* --- Project page photo strip: broken photo -> placeholder frame ---
   A photo that fails to load turns its frame back into a "More photos
   coming soon" placeholder instead of a broken-image icon. Was an inline
   onerror="" on each <img>, which the Content-Security-Policy no longer
   runs. A photo that already failed before this script ran is caught by
   the complete/naturalWidth check. */
function initProgramFrameFallback() {
  document.querySelectorAll('.program-frame.has-photo img').forEach((img) => {
    const fail = () => img.closest('.program-frame').classList.remove('has-photo');
    if (img.complete && img.naturalWidth === 0 && img.currentSrc) fail();
    else img.addEventListener('error', fail, { once: true });
  });
}

/* --- Current-Page Nav Indicator ---
   The home page ("/") highlights nav items by scroll position instead (see
   initSmoothScroll's scroll-spy) since it's a single-page section flow.
   Everywhere else, mark the nav link(s) matching the current file, and
   the parent dropdown toggle, as active. Program detail pages aren't in
   the nav directly, so they map up to their category's hub page. */
function initActiveNav() {
  const currentPage = (window.location.pathname.split('/').pop() || 'index').toLowerCase();
  if (!currentPage || currentPage === 'index') return;

  const categoryParent = {
    'bright-beginnings': 'active-projects',
    'learning-centers': 'active-projects',
    'model-schools': 'active-projects',
    'language-labs': 'active-projects',
    'teacher-training': 'active-projects',
    'research-hub': 'active-projects',
    'skills-development': 'active-projects',
    'secondary-scholarships': 'active-projects',
    'rising-stars': 'active-projects',
  };
  const effectivePage = categoryParent[currentPage] || currentPage;

  const navLinks = document.querySelectorAll(
    '.nav-menu > a, .nav-dropdown > .dropdown-toggle, .dropdown-menu a'
  );

  navLinks.forEach((link) => {
    const href = link.getAttribute('href');
    if (!href) return;
    // Leading slash stripped so both "/" (home) and relative "programs"
    // style hrefs compare the same way against currentPage.
    const hrefPage = href.split('#')[0].replace(/^\//, '').toLowerCase();
    if (!hrefPage || hrefPage === 'index') return;
    if (hrefPage === currentPage || hrefPage === effectivePage) {
      link.classList.add('active');
      const dropdown = link.closest('.nav-dropdown');
      if (dropdown) {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        if (toggle) toggle.classList.add('active');
      }
    }
  });
}

/* --- Header Scroll Effect --- */
function initHeader() {
  const header = document.getElementById('main-header');
  let lastScroll = 0;

  function onScroll() {
    const scrollY = window.scrollY;
    if (scrollY > 80) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
    lastScroll = scrollY;
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // initial check
}

/* --- Hero Glass Slideshow --- */
function initSlideshow() {
  const slides = document.querySelectorAll('#hero-slideshow .hero-slide');
  const textSlides = document.querySelectorAll('.hero-text-slide');
  const dots = document.querySelectorAll('#hero-slide-dots .slide-dot');
  if (!slides.length) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let current = 0;
  let interval;
  let activeTypewriterTimeout = null;
  // Outgoing slide -> the timer that releases it after its fade (showSlide).
  const leavingTimers = new Map();

  /* --- Deferred slide images ---------------------------------------------
     All but the first slide ship with their URL in data-src rather than src,
     so the homepage's initial load fetches one hero image instead of nine.
     See the comment on the slideshow markup in index.php for why
     loading="lazy" could not do this job.

     A slide is hydrated one step before it is needed, which is enough time to
     download at the 6-second rotation this slideshow runs at, and means a
     visitor who leaves after the first slide has cost themselves nothing. */
  function hydrateSlide(index) {
    const slide = slides[index];
    if (!slide) return;

    const img = slide.querySelector('img[data-src]');
    if (!img) return;

    // srcset first: assigning src while srcset is still absent would start a
    // download of the full-size original that the srcset candidate then
    // replaces, fetching two copies of the same photo.
    if (img.dataset.srcset) {
      img.srcset = img.dataset.srcset;
      img.sizes = img.dataset.sizes || '100vw';
      delete img.dataset.srcset;
      delete img.dataset.sizes;
    }

    img.src = img.dataset.src;
    delete img.dataset.src;
  }

  /* The very first look-ahead waits for the load event. showSlide(0) runs at
     DOMContentLoaded, and fetching slide 2 there would put it in direct
     competition with the LCP image for the same early bandwidth — which is
     the problem this whole change exists to fix. Rotation is 8 seconds, so
     there is ample time either way. Every later look-ahead is immediate. */
  let pageLoaded = document.readyState === 'complete';

  if (!pageLoaded) {
    window.addEventListener('load', () => { pageLoaded = true; }, { once: true });
  }

  function hydrateNext(index) {
    const next = (index + 1) % slides.length;

    if (pageLoaded) {
      hydrateSlide(next);
      return;
    }

    window.addEventListener('load', () => hydrateSlide(next), { once: true });
  }

  function showSlide(index) {
    // This slide (a dot can jump straight to one that was never next) and the
    // one after it.
    hydrateSlide(index);
    hydrateNext(index);

    // Deactivate all image slides — resets their Ken Burns animation.
    // The outgoing photo stays solid underneath (.is-leaving) while the new
    // one fades in over it. Removing .active also stops its slow zoom or
    // pan, which snapped it back to its starting size in one frame while it
    // was still in view, so its transform is held where the animation had
    // got to. Both are released once the 1.2s fade has finished.
    slides.forEach((s, i) => {
      const img = s.querySelector('img');
      if (img && s.classList.contains('active') && i !== index) {
        img.style.transform = getComputedStyle(img).transform;
        s.classList.add('is-leaving');
        clearTimeout(leavingTimers.get(s));
        leavingTimers.set(s, setTimeout(() => {
          s.classList.remove('is-leaving');
          img.style.transform = '';
          leavingTimers.delete(s);
        }, 1300));
      }
      s.classList.remove('active');
      // Force animation restart by re-triggering reflow
      void s.offsetWidth;
    });

    // The incoming photo starts afresh: not held over from when it last
    // left (a dot pressed quickly can bring it straight back).
    const incoming = slides[index];
    if (leavingTimers.has(incoming)) {
      clearTimeout(leavingTimers.get(incoming));
      leavingTimers.delete(incoming);
      incoming.classList.remove('is-leaving');
      const incomingImg = incoming.querySelector('img');
      if (incomingImg) incomingImg.style.transform = '';
    }

    // Deactivate all text slides. aria-hidden keeps every inactive slide's
    // <h1> out of the accessibility tree — otherwise a screen reader
    // encounters four stacked H1s (opacity alone doesn't remove content
    // from the a11y tree the way aria-hidden/display:none does).
    textSlides.forEach((ts) => {
      ts.classList.remove('active');
      ts.setAttribute('aria-hidden', 'true');
    });

    // Activate new image slide
    slides[index].classList.add('active');

    // Activate new text slide
    if (textSlides[index]) {
      textSlides[index].classList.add('active');
      textSlides[index].setAttribute('aria-hidden', 'false');
      if (prefersReducedMotion) {
        showFullText(textSlides[index]);
      } else {
        runSlideTypewriter(textSlides[index]);
      }
    }

    // Update 3 Dynamic Window Dots (Left = Previous, Center = Current Active, Right = Next)
    const prevDot = document.getElementById('hero-prev-dot');
    const currDot = document.getElementById('hero-curr-dot');
    const nextDot = document.getElementById('hero-next-dot');

    const total = slides.length;
    current = index;
    const prevIndex = (current - 1 + total) % total;
    const nextIndex = (current + 1) % total;

    if (prevDot) {
      prevDot.dataset.slide = prevIndex;
      prevDot.setAttribute('aria-label', `Previous Program (${prevIndex + 1})`);
    }
    if (currDot) {
      currDot.dataset.slide = current;
      currDot.classList.add('active');
      currDot.setAttribute('aria-label', `Current Program (${current + 1} of ${total})`);
    }
    if (nextDot) {
      nextDot.dataset.slide = nextIndex;
      nextDot.setAttribute('aria-label', `Next Program (${nextIndex + 1})`);
    }

    // --- Update Project Cards (Mobile Carousel Sync) ---
    const updateCards = document.querySelectorAll('.updates-grid .updates-card');
    updateCards.forEach(card => card.classList.remove('mobile-active'));
    if (updateCards[index]) {
      updateCards[index].classList.add('mobile-active');
    }
  }

  // Reduced-motion equivalent of the typewriter effect: show the finished
  // heading immediately instead of animating it character by character.
  function showFullText(activeSlide) {
    const staticSpan = activeSlide.querySelector('.static-part');
    const highlightSpan = activeSlide.querySelector('.highlight-part');
    if (!staticSpan || !highlightSpan) return;

    staticSpan.textContent = staticSpan.getAttribute('data-text');
    highlightSpan.textContent = highlightSpan.getAttribute('data-text');
  }

  // Types the heading out letter by letter: the lead, then the project name.
  // There is no cursor: the green bar that followed the letters went on
  // blinking beside the finished name.
  function runSlideTypewriter(activeSlide) {
    // Clear any pending typewriter timeout
    if (activeTypewriterTimeout) {
      clearTimeout(activeTypewriterTimeout);
    }

    const staticSpan = activeSlide.querySelector('.static-part');
    const highlightSpan = activeSlide.querySelector('.highlight-part');
    if (!staticSpan || !highlightSpan) return;

    const staticText = staticSpan.getAttribute('data-text');
    const highlightText = highlightSpan.getAttribute('data-text');

    // Clear both text elements
    staticSpan.textContent = '';
    highlightSpan.textContent = '';

    let staticCharIndex = 0;
    let highlightCharIndex = 0;
    const typingSpeed = 65; // Professional, slightly slower typing speed

    function typeStatic() {
      if (staticCharIndex < staticText.length) {
        staticSpan.textContent += staticText.charAt(staticCharIndex);
        staticCharIndex++;
        activeTypewriterTimeout = setTimeout(typeStatic, typingSpeed);
      } else {
        typeHighlight();
      }
    }

    function typeHighlight() {
      if (highlightCharIndex < highlightText.length) {
        highlightSpan.textContent += highlightText.charAt(highlightCharIndex);
        highlightCharIndex++;
        activeTypewriterTimeout = setTimeout(typeHighlight, typingSpeed);
      }
    }

    // Start typing after slide settles (200ms delay)
    activeTypewriterTimeout = setTimeout(typeStatic, 200);
  }

  function nextSlide() {
    showSlide((current + 1) % slides.length);
  }

  function startAutoPlay() {
    if (prefersReducedMotion) return; // WCAG 2.2.2: don't auto-advance content for users who asked for less motion
    interval = setInterval(nextSlide, 8000); // 3 seconds interval
  }

  function stopAutoPlay() {
    clearInterval(interval);
  }

  // 3 Dynamic Window Dots Click Navigation (Left = Previous, Center = Current, Right = Next)
  const prevDotBtn = document.getElementById('hero-prev-dot');
  const currDotBtn = document.getElementById('hero-curr-dot');
  const nextDotBtn = document.getElementById('hero-next-dot');

  [prevDotBtn, currDotBtn, nextDotBtn].forEach((dot) => {
    if (!dot) return;
    dot.addEventListener('click', () => {
      stopAutoPlay();
      const targetIndex = parseInt(dot.dataset.slide, 10);
      if (!isNaN(targetIndex)) {
        showSlide(targetIndex);
      }
      startAutoPlay();
    });
  });

  // Pause on hover
  const hero = document.getElementById('home');
  if (hero) {
    // hero.addEventListener('mouseenter', stopAutoPlay);
    // hero.addEventListener('mouseleave', startAutoPlay);
  }

  // --- Mobile Project Cards Navigation Sync ---
  const mobileUpdatesPrev = document.getElementById('mobile-updates-prev');
  const mobileUpdatesNext = document.getElementById('mobile-updates-next');
  
  if (mobileUpdatesPrev) {
    mobileUpdatesPrev.addEventListener('click', () => {
      stopAutoPlay();
      const total = slides.length;
      showSlide((current - 1 + total) % total);
      startAutoPlay();
    });
  }
  
  if (mobileUpdatesNext) {
    mobileUpdatesNext.addEventListener('click', () => {
      stopAutoPlay();
      showSlide((current + 1) % slides.length);
      startAutoPlay();
    });
  }

  // Initialize
  showSlide(0);
  startAutoPlay();
}

/* --- Scroll Reveal Animations --- */
function initScrollReveal() {
  const reveals = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('active');
          // Reveal once, then leave it - removing 'active' on exit made
          // every section re-play its entrance animation on every
          // scroll-up past it, which reads as the page re-rendering
          // itself rather than a one-time entrance.
          observer.unobserve(entry.target);
        }
      });
    },
    {
      threshold: 0.15,
      rootMargin: '0px 0px -50px 0px',
    }
  );

  reveals.forEach((el) => observer.observe(el));
}

/* --- Counter Animation --- */
function initCounterAnimation() {
  const counters = document.querySelectorAll('[data-count]');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // Was declared but never read - every scroll back past the threshold
  // re-ran the count-up from 0 on numbers already showing their final
  // value. Now actually gates it, and the observer disconnects once run
  // since there is nothing left for it to do.
  let animated = false;

  function setFinal(counter) {
    const target = parseInt(counter.dataset.count, 10);
    const suffix = counter.dataset.suffix || '';
    const prefix = counter.dataset.prefix || '';
    counter.textContent = prefix + target.toLocaleString() + suffix;
  }

  if (reduceMotion) {
    counters.forEach(setFinal);
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting && !animated) {
          animated = true;
          observer.disconnect();
          counters.forEach((counter) => {
            const target = parseInt(counter.dataset.count, 10);
            const suffix = counter.dataset.suffix || '';
            const prefix = counter.dataset.prefix || '';
            const duration = 2000;
            const step = Math.ceil(target / (duration / 16));
            let current = 0;

            function update() {
              current += step;
              if (current >= target) {
                current = target;
                counter.textContent = prefix + current.toLocaleString() + suffix;
                return;
              }
              counter.textContent = prefix + current.toLocaleString() + suffix;
              requestAnimationFrame(update);
            }

            requestAnimationFrame(update);
          });
        }
      });
    },
    { threshold: 0.3 }
  );

  // Observe both old and new impact sections
  const impactSection = document.getElementById('impact');
  const impactBar = document.getElementById('impact-bar');
  if (impactSection) observer.observe(impactSection);
  if (impactBar) observer.observe(impactBar);
}

/* --- Mobile Menu --- */
function initMobileMenu() {
  const hamburger = document.getElementById('hamburger');
  const navMenu = document.getElementById('nav-menu');
  const overlay = document.getElementById('mobile-overlay');
  const navLinks = navMenu ? navMenu.querySelectorAll('a') : [];

  function getFocusable() {
    return Array.from(navMenu.querySelectorAll('a[href], button:not([disabled])'))
      .filter((el) => el.offsetParent !== null);
  }

  function closeMenu(returnFocus) {
    hamburger.classList.remove('active');
    navMenu.classList.remove('active');
    overlay.classList.remove('active');
    hamburger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    if (returnFocus) hamburger.focus();
  }

  function openMenu() {
    hamburger.classList.add('active');
    navMenu.classList.add('active');
    overlay.classList.add('active');
    hamburger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    const focusable = getFocusable();
    if (focusable.length) focusable[0].focus();
  }

  if (hamburger) {
    hamburger.addEventListener('click', () => {
      if (navMenu.classList.contains('active')) {
        closeMenu(false);
      } else {
        openMenu();
      }
    });
  }

  if (overlay) {
    overlay.addEventListener('click', () => closeMenu(false));
  }

  // Escape closes the drawer and returns focus to the toggle button - a
  // keyboard user must never be left with no way out of an open overlay.
  // Tab/Shift+Tab wrap within the drawer's focusable elements while it's
  // open, so focus can't silently leave into content hidden behind it.
  document.addEventListener('keydown', (e) => {
    if (!navMenu.classList.contains('active')) return;
    if (e.key === 'Escape') {
      closeMenu(true);
      return;
    }
    if (e.key === 'Tab') {
      const focusable = getFocusable();
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  // Dropdown toggles open their submenu on tap wherever hover can't: inside
  // the drawer (which runs up to 1099px - checking <=768 here left tablets'
  // drawer toggles navigating away), and on touch screens using the full
  // menu, e.g. tablets in landscape, where hover-to-open never fires. In
  // the full menu a second tap on an open toggle follows its link.
  const canHover = window.matchMedia('(hover: hover) and (pointer: fine)');
  const drawerMode = () => !!hamburger && getComputedStyle(hamburger).display !== 'none';
  const dropdownToggles = navMenu ? navMenu.querySelectorAll('.dropdown-toggle') : [];
  dropdownToggles.forEach((toggle) => {
    toggle.addEventListener('click', (e) => {
      const parent = toggle.parentElement;
      const drawer = drawerMode();
      if (drawer || (!canHover.matches && !parent.classList.contains('active'))) {
        e.preventDefault(); // Prevent navigating to the link immediately
        const nowActive = !parent.classList.contains('active');
        parent.classList.toggle('active', nowActive);
        toggle.setAttribute('aria-expanded', String(nowActive));

        // Close other open dropdowns
        dropdownToggles.forEach((otherToggle) => {
          if (otherToggle !== toggle) {
            otherToggle.parentElement.classList.remove('active');
            otherToggle.setAttribute('aria-expanded', 'false');
          }
        });
      }
    });
  });

  // Full menu on touch: a tap anywhere else closes an open submenu.
  document.addEventListener('click', (e) => {
    if (drawerMode() || e.target.closest('.nav-dropdown')) return;
    dropdownToggles.forEach((toggle) => {
      toggle.parentElement.classList.remove('active');
      toggle.setAttribute('aria-expanded', 'false');
    });
  });

  navLinks.forEach((link) => {
    if (!link.classList.contains('dropdown-toggle')) {
      link.addEventListener('click', () => closeMenu(false));
    }
  });
}

/* --- Gallery Lightbox --- */
function initGalleryLightbox() {
  const galleryItems = document.querySelectorAll('.gallery-item');
  const lightbox = document.getElementById('lightbox');
  const lightboxImg = document.getElementById('lightbox-img');
  const lightboxClose = document.getElementById('lightbox-close');
  const lightboxPrev = document.getElementById('lightbox-prev');
  const lightboxNext = document.getElementById('lightbox-next');
  let currentIndex = 0;
  let images = [];

  // Built at click time from the tiles currently shown, so with a project
  // filter on (js/gallery.js) the arrows step through that project's
  // photos only.
  galleryItems.forEach((item) => {
    if (!item.querySelector('img')) return;
    item.addEventListener('click', () => {
      const visible = Array.from(galleryItems).filter((el) => !el.hidden && el.querySelector('img'));
      images = visible.map((el) => el.querySelector('img').src);
      currentIndex = Math.max(0, visible.indexOf(item));
      openLightbox(images[currentIndex]);
    });
  });

  const globalMap = document.getElementById('globally-map-img');
  if (globalMap) {
    const mapWrapper = globalMap.closest('.globally-map-wrapper');
    const trigger = mapWrapper || globalMap;
    trigger.addEventListener('click', () => {
      openLightbox(globalMap.src);
    });
  }

  function openLightbox(src) {
    lightboxImg.src = src;
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lightbox.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (lightboxClose) {
    lightboxClose.addEventListener('click', closeLightbox);
  }

  if (lightbox) {
    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox) closeLightbox();
    });
  }

  if (lightboxPrev) {
    lightboxPrev.addEventListener('click', (e) => {
      e.stopPropagation();
      currentIndex = (currentIndex - 1 + images.length) % images.length;
      lightboxImg.src = images[currentIndex];
    });
  }

  if (lightboxNext) {
    lightboxNext.addEventListener('click', (e) => {
      e.stopPropagation();
      currentIndex = (currentIndex + 1) % images.length;
      lightboxImg.src = images[currentIndex];
    });
  }

  // Keyboard navigation
  document.addEventListener('keydown', (e) => {
    if (!lightbox || !lightbox.classList.contains('active')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') {
      currentIndex = (currentIndex - 1 + images.length) % images.length;
      lightboxImg.src = images[currentIndex];
    }
    if (e.key === 'ArrowRight') {
      currentIndex = (currentIndex + 1) % images.length;
      lightboxImg.src = images[currentIndex];
    }
  });
}

/* --- Smooth Scroll ---
   Nav/footer links use both bare "#hash" and "/#hash" forms (dropdown
   links need the leading "/" so they work from other pages).
   Only intercept a click when the hash target actually exists on the
   current page — otherwise let the browser navigate there normally. */
function initSmoothScroll() {
  const links = document.querySelectorAll('a[href*="#"]');

  links.forEach((link) => {
    const href = link.getAttribute('href');
    const hashIndex = href.indexOf('#');
    if (hashIndex === -1) return;
    const hash = href.slice(hashIndex);
    if (hash === '#') return;

    let target;
    try {
      target = document.querySelector(hash);
    } catch (err) {
      return;
    }
    if (!target) return;

    link.addEventListener('click', (e) => {
      e.preventDefault();
      const headerOffset = 80;
      const elementPosition = target.getBoundingClientRect().top;
      const offsetPosition = elementPosition + window.scrollY - headerOffset;
      window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth',
      });
    });
  });

  // Active nav link highlighting (scroll-spy), matched by hash fragment
  // alone so it works regardless of the "/#hash" prefix.
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-menu a[href*="#"]');

  if (sections.length && navLinks.length) {
    window.addEventListener(
      'scroll',
      () => {
        let current = '';
        sections.forEach((section) => {
          const sectionTop = section.offsetTop - 120;
          if (window.scrollY >= sectionTop) {
            current = section.getAttribute('id');
          }
        });
        navLinks.forEach((link) => {
          const linkHash = link.getAttribute('href').split('#')[1];
          link.classList.toggle('active', Boolean(current) && linkHash === current);
        });
      },
      { passive: true }
    );
  }
}

/* --- Contact / Volunteer Form ---
 *
 * This used to be a lie. It called preventDefault(), waited 1.5s, showed
 * "✓ Message Sent!" and reset the fields — while posting nothing anywhere.
 * Every enquiry a visitor typed was thrown away and they were thanked for it.
 *
 * Now it posts to api/contact.php and reports what actually happened. The form
 * carries a real method/action, so this is a progressive enhancement: with
 * this script blocked the browser submits normally and the endpoint redirects
 * back with a result banner. The only thing the fetch() adds is staying on the
 * page.
 */
function initContactForm() {
  const form = document.getElementById('contact-form');
  if (!form) return;

  const status = form.querySelector('[data-form-status]');
  const btn = form.querySelector('button[type="submit"]');
  if (!status || !btn) return;

  // A banner rendered by the server (the no-JS path landing back here with
  // ?form=…) is stale the moment someone starts editing the form again.
  form.addEventListener('input', clearErrors, { once: true });

  function clearErrors() {
    form.querySelectorAll('[data-error-for]').forEach((el) => {
      el.textContent = '';
      el.hidden = true;
    });
    form.querySelectorAll('[aria-invalid]').forEach((el) => {
      el.removeAttribute('aria-invalid');
    });
  }

  function showStatus(type, message) {
    status.textContent = message;
    status.className = 'form-status form-status--' + type;
    status.hidden = false;
  }

  /* Marks the specific inputs the server rejected. Wiring aria-invalid and
     aria-describedby here rather than in the markup keeps the fields clean
     until there is genuinely something wrong with them. */
  function showFieldErrors(errors) {
    Object.keys(errors || {}).forEach((field) => {
      const message = form.querySelector('[data-error-for="' + field + '"]');
      const input = form.elements[field];
      if (message) {
        message.textContent = errors[field];
        message.hidden = false;
        if (message.id === '') message.id = 'error-' + field;
      }
      if (input && input.setAttribute) {
        input.setAttribute('aria-invalid', 'true');
        if (message) input.setAttribute('aria-describedby', message.id);
      }
    });

    // Send focus to the first problem so a keyboard or screen-reader user is
    // taken to it rather than told, somewhere above, that something is wrong.
    const firstField = Object.keys(errors || {})[0];
    const firstInput = firstField && form.elements[firstField];
    if (firstInput && firstInput.focus) firstInput.focus();
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();

    // novalidate is set on the form so the server is always the authority on
    // what is acceptable; this runs the browser's own checks first anyway, so
    // an empty required field is caught without a round trip.
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const originalText = btn.textContent;
    btn.textContent = 'Sending...';
    btn.disabled = true;
    showStatus('pending', 'Sending your message…');

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        // What tells the endpoint to answer with JSON instead of a redirect.
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      const result = await response.json();

      if (result.ok) {
        showStatus('success', result.message);
        form.reset();
        btn.textContent = '✓ Message Sent!';
        // Left disabled: the message is stored, and re-enabling the button
        // under a "Sent" label invites the double submission that produces two
        // identical rows for someone to triage twice.
        return;
      }

      showStatus('error', result.message);
      showFieldErrors(result.errors);
    } catch (err) {
      // A network failure or a non-JSON response (a proxy error page, an
      // offline device). The message must not imply the enquiry was received.
      showStatus(
        'error',
        'We could not send that — please check your connection and try again, or email info@wefdallas.org.'
      );
    } finally {
      if (btn.textContent === 'Sending...') {
        btn.textContent = originalText;
      }
      if (btn.textContent !== '✓ Message Sent!') {
        btn.disabled = false;
      }
    }
  });
}

/* --- Footer Newsletter Form --- */
/* --- Footer Newsletter Form ---
 *
 * This was the second fake form on the site. It validated the address in the
 * browser, waited 800ms, and told the visitor "You're subscribed — thank you
 * for supporting WEF." having sent nothing anywhere. That was on two pages;
 * consolidating every page onto footer-full would have put it on all 24, so
 * it now posts to api/contact.php as form_type=newsletter and the address
 * lands in the same CMS inbox as the contact and volunteer forms.
 *
 * Progressive enhancement, like the contact form: the markup carries a real
 * method and action, so with this script blocked the browser submits normally
 * and the endpoint redirects back with a result banner.
 */
function initNewsletterForm() {
  const form = document.querySelector('.footer-newsletter-form');
  if (!form) return;

  const input = form.querySelector('input[type="email"]');
  const button = form.querySelector('button');
  const feedback = document.getElementById('newsletter-feedback');
  if (!input || !button) return;

  const originalLabel = button.textContent;
  const errorColor = getComputedStyle(document.documentElement).getPropertyValue('--clr-error').trim() || '#e02424';

  function setFeedback(message, isError) {
    if (!feedback) return;
    feedback.textContent = message;
    feedback.classList.toggle('is-error', Boolean(isError));
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    // The browser's own check first, so an obvious typo costs no round trip.
    // The server validates regardless — it is the only authority here.
    if (!form.checkValidity()) {
      input.focus();
      input.style.borderColor = errorColor;
      setFeedback('Please enter a valid email address.', true);
      setTimeout(() => { input.style.borderColor = ''; }, 1500);
      return;
    }

    button.disabled = true;
    button.textContent = '…';
    setFeedback('', false);

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const result = await response.json();

      if (result.ok) {
        button.textContent = '✓';
        input.value = '';
        setFeedback(result.message, false);
        // Left disabled on success: the address is stored, and re-arming the
        // button under a tick invites a duplicate row for the same person.
        return;
      }

      // Covers the flood limit as well as a rejected address, and says what
      // the server actually said rather than inventing a success.
      setFeedback(result.errors?.email || result.message, true);
    } catch (err) {
      setFeedback('We could not sign you up just now — please try again shortly.', true);
    } finally {
      if (button.textContent !== '✓') {
        button.textContent = originalLabel;
        button.disabled = false;
      }
    }
  });
}

/* --- Before / After Comparison Slider ---
   Builds itself from a bare <div class="compare-slider" data-before="..."
   data-before-label="Before" data-after="..." data-after-label="After">.
   Drag, click, or use the Left/Right arrow keys (Home/End for the ends). */
function initCompareSlider() {
  const sliders = document.querySelectorAll('.compare-slider');
  if (!sliders.length) return;

  sliders.forEach((el) => {
    const beforeSrc = el.dataset.before;
    const afterSrc = el.dataset.after;
    if (!beforeSrc || !afterSrc) return;

    const beforeLabel = el.dataset.beforeLabel || 'Before';
    const afterLabel = el.dataset.afterLabel || 'After';
    const beforeAlt = el.dataset.beforeAlt || beforeLabel;
    const afterAlt = el.dataset.afterAlt || afterLabel;

    el.innerHTML = `
      <img class="compare-slider-img" src="${afterSrc}" alt="${afterAlt}" loading="lazy">
      <div class="compare-slider-before-wrap">
        <img class="compare-slider-img" src="${beforeSrc}" alt="${beforeAlt}" loading="lazy">
      </div>
      <span class="compare-slider-label compare-slider-label-before">${beforeLabel}</span>
      <span class="compare-slider-label compare-slider-label-after">${afterLabel}</span>
      <div class="compare-slider-handle">
        <div class="compare-slider-handle-line"></div>
        <div class="compare-slider-handle-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 7l-5 5 5 5M16 7l5 5-5 5"/>
          </svg>
        </div>
      </div>
    `;

    el.setAttribute('role', 'slider');
    el.setAttribute('aria-label', `Before and after comparison: ${beforeLabel} versus ${afterLabel}`);
    el.setAttribute('aria-valuemin', '0');
    el.setAttribute('aria-valuemax', '100');
    if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '0');

    let pos = 50;

    function setPos(value) {
      pos = Math.min(100, Math.max(0, value));
      el.style.setProperty('--compare-pos', pos + '%');
      el.setAttribute('aria-valuenow', String(Math.round(pos)));
    }

    function posFromClientX(clientX) {
      const rect = el.getBoundingClientRect();
      return ((clientX - rect.left) / rect.width) * 100;
    }

    let dragging = false;

    el.addEventListener('pointerdown', (e) => {
      dragging = true;
      el.setPointerCapture(e.pointerId);
      setPos(posFromClientX(e.clientX));
    });

    el.addEventListener('pointermove', (e) => {
      if (!dragging) return;
      setPos(posFromClientX(e.clientX));
    });

    ['pointerup', 'pointercancel', 'pointerleave'].forEach((evt) => {
      el.addEventListener(evt, () => { dragging = false; });
    });

    el.addEventListener('keydown', (e) => {
      const step = e.shiftKey ? 20 : 5;
      if (e.key === 'ArrowLeft') { setPos(pos - step); e.preventDefault(); }
      else if (e.key === 'ArrowRight') { setPos(pos + step); e.preventDefault(); }
      else if (e.key === 'Home') { setPos(0); e.preventDefault(); }
      else if (e.key === 'End') { setPos(100); e.preventDefault(); }
    });

    setPos(50);
  });
}

/* --- Card carousels ---
   Drives the prev/next arrow buttons on every .card-carousel (see the CSS
   component of the same name in styles.css). The swipe/drag itself needs
   no JS at all - overflow-x:auto + scroll-snap-type on .card-carousel-track
   gives native touch scrolling for free, and this only has to (a) move the
   track by one card's width when an arrow is clicked and (b) disable
   whichever arrow points past the current scroll position, so a keyboard
   or screen-reader-adjacent user isn't shown a control that does nothing.
   One instance of this logic serves every carousel on the page - each
   .card-carousel is independent, matched by its own nearest track/arrows. */
function initCardCarousels() {
  // .updates-carousel: Project Updates, a carousel from 769px up only (see
  // styles.css); on phones its arrows are hidden and nothing scrolls.
  document.querySelectorAll('.card-carousel, .updates-carousel').forEach((carousel) => {
    const track = carousel.querySelector('.card-carousel-track, .updates-grid');
    const prevBtn = carousel.querySelector('.carousel-arrow--prev');
    const nextBtn = carousel.querySelector('.carousel-arrow--next');
    if (!track || !prevBtn || !nextBtn) return;

    function cardStep() {
      const first = track.firstElementChild;
      if (!first) return track.clientWidth;
      const style = getComputedStyle(track);
      const gap = parseFloat(style.columnGap || style.gap || '0') || 0;
      return first.getBoundingClientRect().width + gap;
    }

    function updateArrowState() {
      const maxScroll = track.scrollWidth - track.clientWidth;
      // 2px slack absorbs sub-pixel rounding from scroll-snap settling.
      prevBtn.disabled = track.scrollLeft <= 2;
      nextBtn.disabled = track.scrollLeft >= maxScroll - 2;
    }

    prevBtn.addEventListener('click', () => {
      track.scrollBy({ left: -cardStep(), behavior: 'smooth' });
    });
    nextBtn.addEventListener('click', () => {
      track.scrollBy({ left: cardStep(), behavior: 'smooth' });
    });

    track.addEventListener('scroll', updateArrowState, { passive: true });
    window.addEventListener('resize', updateArrowState);

    // Mouse drag at phone widths (a narrowed desktop window, touch-less
    // tablets): an overflow scroller only scrolls by touch or wheel, so a
    // mouse drag did nothing and the row read as "doesn't swipe". Snap is
    // paused during the drag and restored on release, so it settles on a
    // card; a drag suppresses the click that would otherwise open the card.
    const phone = window.matchMedia('(max-width: 768px)');
    let drag = null;
    track.addEventListener('pointerdown', (e) => {
      if (e.pointerType !== 'mouse' || e.button !== 0 || !phone.matches) return;
      drag = { x: e.clientX, left: track.scrollLeft, moved: false };
    });
    track.addEventListener('pointermove', (e) => {
      if (!drag) return;
      const dx = e.clientX - drag.x;
      if (!drag.moved && Math.abs(dx) < 6) return;
      if (!drag.moved) {
        drag.moved = true;
        track.setPointerCapture(e.pointerId);
        track.style.scrollSnapType = 'none';
        track.style.scrollBehavior = 'auto';
      }
      track.scrollLeft = drag.left - dx;
    });
    const endDrag = () => {
      if (!drag) return;
      if (drag.moved) {
        track.style.scrollSnapType = '';
        track.style.scrollBehavior = '';
        const block = (ev) => { ev.preventDefault(); ev.stopPropagation(); };
        track.addEventListener('click', block, { capture: true, once: true });
        setTimeout(() => track.removeEventListener('click', block, { capture: true }), 0);
      }
      drag = null;
    };
    track.addEventListener('pointerup', endDrag);
    track.addEventListener('pointercancel', endDrag);
    track.addEventListener('dragstart', (e) => { if (phone.matches) e.preventDefault(); });

    // The very first updateArrowState() call, synchronous at page-load time,
    // can measure the track before its cards' images have finished loading
    // and widened it - scrollWidth briefly equals clientWidth, "next" gets
    // disabled, and neither the scroll nor resize listener above ever fires
    // to correct it (nothing scrolled or resized; the content just grew
    // under it). A ResizeObserver catches that reflow directly, and any
    // other reason the track's own box might change size later (a card
    // image swapped, a web font finishing its swap, orientation change)
    // without having to guess and wire up each cause by hand.
    if (window.ResizeObserver) {
      new ResizeObserver(updateArrowState).observe(track);
    }
    updateArrowState();
  });
}

/* --- Honor Board "show more" ---
   Each sponsor group (47 sponsors across 4 groups, all rendered
   unconditionally before this) now caps how many tiles show at once,
   scaling with viewport width so the board doesn't dominate a phone
   screen with one long scroll of tiles. A group whose sponsor count
   already fits under the current cap gets no button at all - re-checked
   on resize, not just at load, so rotating a tablet from portrait to
   landscape (crossing the 640px/1024px steps below) can make a button
   that was needed a moment ago disappear, or vice versa. */
function initHonorBoardExpand() {
  // Desktop (1024px+) has the room to list every sponsor, so there is no
  // cap and no Show More button. Smaller screens show two full rows of
  // their 3-column grid (styles.css) and expand on demand.
  function capForWidth() {
    if (window.innerWidth >= 1024) return Infinity;
    return 6;
  }

  document.querySelectorAll('.sponsor-group').forEach((group) => {
    const grid = group.querySelector('.sponsors-grid');
    if (!grid) return;
    const tiles = Array.from(grid.children);
    if (tiles.length === 0) return;

    let expanded = false;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'honor-board-show-more';
    grid.insertAdjacentElement('afterend', btn);

    function render() {
      const cap = capForWidth();
      if (tiles.length <= cap) {
        btn.style.display = 'none';
        tiles.forEach((tile) => { tile.style.display = ''; });
        return;
      }
      btn.style.display = '';
      if (expanded) {
        tiles.forEach((tile) => { tile.style.display = ''; });
        btn.textContent = 'Show Less';
      } else {
        tiles.forEach((tile, i) => { tile.style.display = i < cap ? '' : 'none'; });
        btn.textContent = `Show More (${tiles.length - cap} more)`;
      }
    }

    btn.addEventListener('click', () => {
      expanded = !expanded;
      render();
    });
    window.addEventListener('resize', render);
    render();
  });
}

/* --- Honor Board -> map link ---
   Clicking (or Enter/Space-ing) a sponsor tile scrolls to the map and
   filters it to that sponsor's centers - window.toggleSponsor() and
   window.WEF_resolveMapSponsor() are both exposed by map.js, but that
   file only runs once Leaflet lazy-loads (map.js's own IntersectionObserver,
   triggered by scrolling near #map - see the top of that file), which
   this tile's own click is what triggers. So the resolve/filter call has
   to wait for map.js to finish initializing, not just for the scroll to
   land - polled rather than tied to a fixed delay, since load time varies
   with connection speed. Only runs on pages that actually have a Honor
   Board (only index.php, currently) and only wires tiles that carry the
   data-sponsor attribute this feature needs. */
function initHonorBoardMapLink() {
  const tiles = document.querySelectorAll('.sponsor-tile[data-sponsor]');
  const mapSection = document.getElementById('centers-map');
  if (!tiles.length || !mapSection) return;

  function activate(tile) {
    mapSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const name = tile.dataset.sponsor;
    const noResults = document.getElementById('map-no-results');
    const select = document.getElementById('donor-select');
    let attempts = 0;

    (function tryFilter() {
      attempts += 1;
      if (typeof window.WEF_resolveMapSponsor !== 'function' || typeof window.toggleSponsor !== 'function') {
        // Leaflet/map.js hasn't finished loading yet - keep waiting, up to
        // ~9s (a slow connection fetching the ~57KB library + tiles), then
        // give up quietly; a map that failed to load is a separate problem
        // this click isn't responsible for surfacing.
        if (attempts < 60) setTimeout(tryFilter, 150);
        return;
      }

      const resolved = window.WEF_resolveMapSponsor(name);
      if (resolved) {
        window.toggleSponsor(resolved);
        if (select) select.value = resolved;
        if (noResults) noResults.hidden = true;
      } else {
        window.toggleSponsor('All');
        if (select) select.value = 'All';
        if (noResults) {
          const nameEl = noResults.querySelector('[data-sponsor-name]');
          if (nameEl) nameEl.textContent = name;
          noResults.hidden = false;
        }
      }
    })();
  }

  tiles.forEach((tile) => {
    tile.setAttribute('role', 'button');
    tile.setAttribute('tabindex', '0');
    tile.addEventListener('click', () => activate(tile));
    tile.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      e.preventDefault();
      activate(tile);
    });
  });
}

/* --- Active Projects: endless swipe on phones ---
   Clones the last two cards in front of the first and the first two after
   the last, so the row can always be swiped further in both directions.
   When a swipe settles on a clone, the track jumps (no animation) to the
   real card in the identical position - the visible content doesn't move,
   so the jump is invisible. Clones keep their links (a tap still opens the
   right project) but are hidden from screen readers and the tab order.
   Phone-only: clones are removed above 768px, where the arrow-driven,
   bounded carousel stays as it was. */
function initProjectsLoop() {
  const track = document.querySelector('#projects .card-carousel-track');
  if (!track || track.children.length < 3) return;

  const COPIES = 2;
  const mq = window.matchMedia('(max-width: 768px)');
  const originals = Array.from(track.children);
  let clones = [];

  function jumpBy(delta) {
    track.style.scrollBehavior = 'auto';
    track.scrollLeft += delta;
    track.style.scrollBehavior = '';
  }

  function centerDelta(card) {
    const t = track.getBoundingClientRect();
    const c = card.getBoundingClientRect();
    return (c.left + c.width / 2) - (t.left + t.width / 2);
  }

  function makeClone(card) {
    const copy = card.cloneNode(true);
    copy.removeAttribute('id');
    copy.setAttribute('aria-hidden', 'true');
    copy.setAttribute('tabindex', '-1');
    copy.dataset.loopOf = String(originals.indexOf(card));
    return copy;
  }

  function build() {
    if (clones.length) return;
    const head = originals.slice(-COPIES).map(makeClone);
    const tail = originals.slice(0, COPIES).map(makeClone);
    track.prepend(...head);
    track.append(...tail);
    clones = head.concat(tail);
    requestAnimationFrame(() => jumpBy(centerDelta(originals[0])));
  }

  function teardown() {
    clones.forEach((c) => c.remove());
    clones = [];
  }

  function settle() {
    if (!clones.length) return;
    let nearest = null;
    let best = Infinity;
    Array.from(track.children).forEach((card) => {
      const d = Math.abs(centerDelta(card));
      if (d < best) { best = d; nearest = card; }
    });
    if (!nearest || nearest.dataset.loopOf === undefined) return;
    const real = originals[Number(nearest.dataset.loopOf)];
    jumpBy(real.getBoundingClientRect().left - nearest.getBoundingClientRect().left);
  }

  if ('onscrollend' in window) {
    track.addEventListener('scrollend', settle);
  } else {
    let timer;
    track.addEventListener('scroll', () => {
      clearTimeout(timer);
      timer = setTimeout(settle, 150);
    }, { passive: true });
  }

  const apply = () => (mq.matches ? build() : teardown());
  mq.addEventListener('change', apply);
  apply();
}

/* --- Heading character reveal ---
   Vanilla port of Magic UI's
   <TextAnimate animation="slideLeft" by="character">.

   Scope: page and section titles only. The selector matches every h1-h6, then
   CARD_SCOPE below filters out any heading that sits inside a card, because
   the effect is meant for the big titles — not for the heading of every one of
   the 24 team cards or 22 programme cards on a listing page.

   Each surviving heading gets its own character index and its own
   scroll-triggered observer. No `once` (the snippet does not pass it), so a
   title replays each time it scrolls back into view.

   Skipped entirely for prefers-reduced-motion — rather than run a staggered
   sequence that just resolves instantly per-character, reduced-motion users
   get the plain heading with no DOM surgery at all. Any nested element (e.g.
   .purpose-accent) is kept as one unsplit unit rather than split
   per-character, since effects like a background-clip:text gradient need one
   contiguous run of text, not fragments.

   Deliberately excluded:
     - .hero-glass-heading on the homepage, which runs its own per-character
       typewriter effect (see runSlideTypewriter above). Running both on the
       same element would fight over its text content.
     - the Honor Board banner and the Partners heading, which stay static.
     - anything inside a card (CARD_SCOPE), including the sponsor group
       headers that make up the rest of the Honor Board.
     - anything marked data-no-text-animate, as a per-element opt-out that
       does not depend on matching copy or a class name. */
function initCharRevealHeadings() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  /* Card-ish containers whose headings must stay static. Listed explicitly
     rather than matched with a wildcard like [class*="card"]: these are the
     real container classes in this codebase, so the list is checkable against
     the markup and cannot silently start swallowing a section title that
     happens to have "card" somewhere in an ancestor's class. */
  const CARD_SCOPE = [
    '[data-no-text-animate]',
    '.card',
    '.about-card',
    '.team-card-body',
    '.team-modal-body',
    '.program-body',
    '.project-card-body',
    '.updates-card-body',
    '.story-content',
    '.sponsor-group-header',
    '.donate-feature-text',
    '.donate-action-card',
    '.pillar-item-box',
    '.contact-detail-text',
    '.video-info',
  ].join(', ');

    const STAGGER_MS = 45;
    const headings = document.querySelectorAll('h1:not(.hero-glass-heading), h2, h3, h4, h5, h6');

    headings.forEach((el) => {
      if (el.textContent.toLowerCase().includes('partners')) return;
      if (el.textContent.toLowerCase().includes('honor board')) return;
      if (el.closest(CARD_SCOPE)) return;
      let index = 0;
  
      function makeChar(ch) {
        const span = document.createElement('span');
        span.className = 'text-animate-char';
        span.style.transitionDelay = `${index * STAGGER_MS}ms`;
        span.textContent = ch;
        index += 1;
        return span;
      }
  
      function wrapChars(node) {
        Array.from(node.childNodes).forEach((child) => {
          if (child.nodeType === Node.TEXT_NODE) {
            const frag = document.createDocumentFragment();
            child.textContent.split(/( )/).forEach((token) => {
              if (token === '') return;
              if (token === ' ') { frag.appendChild(document.createTextNode(' ')); return; }
                const word = document.createElement('span');
              word.className = 'text-animate-word';
              Array.from(token).forEach((ch) => word.appendChild(makeChar(ch)));
              frag.appendChild(word);
            });
            child.replaceWith(frag);
          } else if (child.nodeType === Node.ELEMENT_NODE) {
            if (child.tagName === 'BR') return;
            child.classList.add('text-animate-char');
            child.style.transitionDelay = `${index * STAGGER_MS}ms`;
            index += 1;
          }
        });
      }
  
      wrapChars(el);
      el.classList.add('text-animate');
  
      // Plays when the title comes into view, and resets on the way out so it
      // plays again next time — the snippet does not pass `once`.
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              el.classList.add('is-animated');
            } else {
              el.classList.remove('is-animated');
            }
          });
        },
        { threshold: 0.1 }
      );

      observer.observe(el);
    });
}

/* --- Impact Goals Chart (/impact-goals) ---
   Triggers the CSS reveal (trend line draw, dot pop-ins, pill/value
   fades — all handled by .in-view in styles.css) and drives the
   count-up on every [data-count] value inside the chart, once it
   scrolls into view. Reduced-motion users get final values immediately
   with no counting animation and no motion (the .in-view class alone is
   enough to show the end state, since transition-duration is already
   zeroed sitewide by the prefers-reduced-motion rule). */
function initImpactGoalsChart() {
  const chart = document.querySelector('.goals-chart');
  if (!chart) return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function animateCounters() {
    chart.querySelectorAll('[data-count]').forEach((counter) => {
      const target = parseInt(counter.dataset.count, 10);

      if (reduceMotion) {
        counter.textContent = target.toLocaleString();
        return;
      }

      const duration = 1400;
      const start = performance.now();

      function update(now) {
        const progress = Math.min(1, (now - start) / duration);
        counter.textContent = Math.round(target * progress).toLocaleString();
        if (progress < 1) requestAnimationFrame(update);
      }

      requestAnimationFrame(update);
    });
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          chart.classList.add('in-view');
          animateCounters();
        } else {
          chart.classList.remove('in-view');
        }
      });
    },
    { threshold: 0.3 }
  );

  observer.observe(chart);
}

/* --- Interactive Donation Amount Selector ---
   Monthly is the default frequency (not just a toggle option): a modest
   recurring gift outraises a larger one-time gift within months, and every
   major nonprofit fundraising benchmark treats "default to monthly" as one
   of the highest-leverage, lowest-cost changes a donation page can make. */
function initDonationTabs() {
  const freqTabs = document.querySelectorAll('.donate-frequency-tab');
  const selectors = {
    monthly: document.getElementById('donate-amount-selector-monthly'),
    once: document.getElementById('donate-amount-selector-once')
  };
  const customToggle = document.getElementById('donate-custom-toggle');
  const customWrap = document.getElementById('donate-custom-amount');
  const customInput = document.getElementById('donate-custom-input');
  const impactText = document.getElementById('donation-impact-text');
  const ctaBtn = document.getElementById('donate-cta-btn');
  const ctaLabel = document.getElementById('donate-cta-label');
  const prompt = document.getElementById('donate-card-prompt');
  const footerNote = document.getElementById('donate-footer-note');
  if (!selectors.monthly || !selectors.once || !impactText || !ctaBtn) return;

  const impacts = {
    monthly: {
      '10': 'Each month, provides school supplies and a hot meal for one child in Chitral.',
      '20': 'Each month, funds a Montessori-trained teacher’s continuing coaching and materials.',
      '35': 'Each month, keeps a language lab’s equipment and internet access running for a full classroom.',
      '50': 'Each month, covers a secondary-school student’s tuition and books toward graduation.',
      '100': 'Each month, sustains an early-childhood center’s teacher salary and daily program costs.'
    },
    once: {
      '25': 'Funds learning supplies and reading books for one child in Chitral.',
      '50': 'Funds technology licenses and English learning software for two students.',
      '100': 'Funds Montessori classroom materials and learning resources for a learning center.',
      '250': 'Funds professional training certification for one local teacher at the university hub.',
      '500': 'Funds a partial undergraduate scholarship for a student at UCA or AUCA.'
    }
  };
  const customImpactDefault = {
    monthly: 'Choose your own monthly amount — every gift goes directly toward WEF’s active projects in Chitral and Central Asia.',
    once: 'Choose your own amount — every dollar goes directly toward WEF’s active projects in Chitral and Central Asia.'
  };
  const copy = {
    monthly: {
      prompt: 'Choose a monthly amount to see your ongoing impact:',
      ctaLabel: 'Become a Monthly Donor',
      footerNote: 'You’ll choose “Monthly” on the next step to set up recurring giving. Secure bank transfer instructions will be sent via email.'
    },
    once: {
      prompt: 'Select an amount below to see the impact of your support:',
      ctaLabel: 'Support Our Mission',
      footerNote: 'Secure bank transfer instructions will be sent via email.'
    }
  };

  let frequency = 'monthly';

  function activeTabs() {
    return selectors[frequency].querySelectorAll('.donate-amount-tab');
  }

  function deactivateCustom() {
    if (customToggle) customToggle.classList.remove('active');
    if (customWrap) customWrap.classList.remove('active');
  }

  function updateDonation(amount) {
    const value = Math.max(1, Math.round(Number(amount) || 0));
    impactText.textContent = impacts[frequency][String(value)] || customImpactDefault[frequency];
  }

  function bindTabs(tabs) {
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        activeTabs().forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        deactivateCustom();
        updateDonation(tab.getAttribute('data-amount'));
      });
    });
  }

  bindTabs(selectors.monthly.querySelectorAll('.donate-amount-tab'));
  bindTabs(selectors.once.querySelectorAll('.donate-amount-tab'));

  freqTabs.forEach(freqTab => {
    freqTab.addEventListener('click', () => {
      const next = freqTab.getAttribute('data-frequency');
      if (next === frequency) return;
      frequency = next;

      freqTabs.forEach(t => t.classList.toggle('active', t === freqTab));
      selectors.monthly.hidden = frequency !== 'monthly';
      selectors.once.hidden = frequency !== 'once';
      deactivateCustom();

      if (prompt) prompt.textContent = copy[frequency].prompt;
      if (ctaLabel) ctaLabel.textContent = copy[frequency].ctaLabel;
      if (footerNote) footerNote.textContent = copy[frequency].footerNote;

      const active = selectors[frequency].querySelector('.donate-amount-tab.active')
        || selectors[frequency].querySelector('.donate-amount-tab');
      if (active) updateDonation(active.getAttribute('data-amount'));
    });
  });

  if (customToggle && customWrap && customInput) {
    customToggle.addEventListener('click', () => {
      activeTabs().forEach(t => t.classList.remove('active'));
      customToggle.classList.add('active');
      customWrap.classList.add('active');
      customInput.focus();

      if (customInput.value && Number(customInput.value) > 0) {
        updateDonation(customInput.value);
      } else {
        impactText.textContent = customImpactDefault[frequency];
      }
    });

    customInput.addEventListener('input', () => {
      if (customInput.value && Number(customInput.value) > 0) {
        updateDonation(customInput.value);
      } else {
        impactText.textContent = customImpactDefault[frequency];
      }
    });
  }

  // Sync the impact text to whichever tab the markup marks active on load
  // (currently monthly $35), rather than relying on hand-written HTML text
  // to stay in sync with the JS copy above by coincidence.
  const initialActive = selectors[frequency].querySelector('.donate-amount-tab.active')
    || selectors[frequency].querySelector('.donate-amount-tab');
  if (initialActive) updateDonation(initialActive.getAttribute('data-amount'));
}

// Mobile Honor Board Expand
document.addEventListener('DOMContentLoaded', () => {
  const expandBtn = document.getElementById('honor-board-expand-btn');
  const honorBoardCard = document.querySelector('.honor-board-card');
  
  if (expandBtn && honorBoardCard) {
    expandBtn.addEventListener('click', () => {
      honorBoardCard.classList.toggle('expanded');
      if (honorBoardCard.classList.contains('expanded')) {
        expandBtn.textContent = 'Show Less';
      } else {
        expandBtn.textContent = 'View Full Honor Board';
        // scroll back up slightly so they don't lose their place
        honorBoardCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }
});

// Project Updates Show All
document.addEventListener('DOMContentLoaded', () => {
  const updatesGrid = document.querySelector('.updates-grid');
  const showAllBtn = document.getElementById('show-all-updates-btn');
  
  if (updatesGrid && showAllBtn) {
    const cards = Array.from(updatesGrid.querySelectorAll('.updates-card'));
    const INITIAL_VISIBLE = 3; // Show only the top row (3 cards)
    
    if (cards.length > INITIAL_VISIBLE) {
      cards.forEach((card, index) => {
        if (index >= INITIAL_VISIBLE) {
          card.style.display = 'none';
        }
      });
    } else {
      showAllBtn.parentElement.style.display = 'none';
    }
    
    showAllBtn.addEventListener('click', () => {
      const isShowingAll = showAllBtn.textContent.includes('Less');
      
      cards.forEach((card, index) => {
        if (index >= INITIAL_VISIBLE) {
          card.style.display = isShowingAll ? 'none' : 'flex';
          // Force a small reflow to allow animations if any
          if (!isShowingAll) {
            card.style.opacity = '0';
            setTimeout(() => {
              card.style.transition = 'opacity 0.4s ease';
              card.style.opacity = '1';
            }, 10);
          } else {
            card.style.opacity = '1';
          }
        }
      });
      
      showAllBtn.textContent = isShowingAll ? 'Show All Updates' : 'Show Less Updates';
      
      if (isShowingAll) {
        updatesGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }
});


/* --- Title Slide Left Scroll Reveal --- */
/* initTitleSlideLeft() was removed here.
   It was a second, whole-title slide animation targeting almost the same
   selectors as initCharRevealHeadings() above, so every heading ran both at
   once -- and they pulled against each other: this one came in from
   translateX(-45px) while the per-character one comes in from +20px, both
   driving opacity. Its .active rule needed !important on both properties,
   the usual sign of two systems fighting over the same declarations. It also
   added a second IntersectionObserver per heading and self-invoked at file
   scope rather than from the init list above.
   The per-character version is the one that was specified (Magic UI
   TextAnimate slideLeft by character); its .title-slide-left CSS is gone too. */

