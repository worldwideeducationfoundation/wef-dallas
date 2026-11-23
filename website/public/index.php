<?php require_once __DIR__ . '/../includes/content-blocks.php';
// Contact details come from the Settings module rather than being repeated
// in markup on three pages. Fallbacks keep the page correct if the row is
// ever missing.
$wefSettings = siteSettings(); ?>
<?php require_once __DIR__ . '/../includes/content.php'; ?>
<?php
// Supplies the subject list the dropdown renders and the fixed messages the
// no-JS result banner picks from — same registry contact.php/volunteer.php
// use, so this page's contact form validates against the same rules instead
// of drifting from them.
require_once __DIR__ . '/../includes/form-submissions.php';
$formType = 'contact';
$formResult = submissionResultMessage($_GET['form'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">

<head>

<?php
  // Social-card and canonical metadata. Values mirror this page's own
  // <title> and description below, so the card and the page always agree.
  $seoTitle = 'Worldwide Education Foundation — Empowering Communities Through Education in Chitral';
  $seoDescription = 'Worldwide Education Foundation (WEF) invests in the potential of children and youth across underserved communities in Chitral, Pakistan through early childhood development, teacher training, English language learning, and scholarships.';
?>
<?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <!-- SEO Meta Tags -->
  <title><?= $seoEsc($seoTitle) ?></title>
  <meta name="description" content="<?= $seoEsc($seoDescription) ?>">
  <meta name="keywords"
    content="WEF, Worldwide Education Foundation, ECD education, Chitral, Pakistan, early childhood development, scholarships, teacher training, NGO">
  <meta name="author" content="Worldwide Education Foundation">

  <!-- Open Graph -->
  <meta property="og:title" content="Worldwide Education Foundation — Empowering Communities Through Education">
  <meta property="og:description"
    content="We invest in the potential of children and youth across underserved communities in Central and South Asia through early development programs, English language learning, and scholarships.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://www.worldwideeducationfoundation.org/">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Worldwide Education Foundation">
  <meta name="twitter:description" content="Empowering communities through quality education in Chitral, Pakistan.">

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="assets/images/logo.png">

  <!-- Leaflet's stylesheet is no longer linked here. It is injected by
       js/map.js when the visitor approaches the map, together with the
       library itself — see the comment at the top of that file. Loading it
       from <head> put a third-party DNS lookup, connection and TLS handshake
       in front of first paint for a widget most visitors never scroll to. -->
  <!-- Stylesheet -->
  <?= asyncStylesheet('css/styles.css') ?>
  <?= asyncStylesheet('css/uiverse-cards.css') ?>

  <?php
  /**
   * LCP preload for the first hero slide.
   *
   * The hero image is the Largest Contentful Paint element on this page, and
   * without this the browser cannot begin fetching it until styles.css has
   * been downloaded and parsed. Announcing it here starts the download in the
   * first round trip, alongside the stylesheet rather than behind it.
   *
   * imagesrcset/imagesizes mirror what the <img> tag will resolve to. They are
   * not optional decoration: a preload of the plain src while the tag carries
   * a srcset makes the browser fetch the preloaded file AND the srcset
   * candidate it actually wants — two images instead of one, which is slower
   * than having no preload at all. The strings must stay in step with the
   * imageTag() call in the markup below, which is why both are generated from
   * the same helpers.
   */
  // Slides come from the admin (Home Slideshow); the preload follows
  // whichever slide is first, so reordering there keeps this correct.
  $heroSlides = publishedHeroSlides();
  $heroImage = $heroSlides[0]['image_src'] ?? 'assets/images/programs/bright-beginnings-01.webp';
  $heroSrcset = imageSrcset($heroImage);
  ?>
  <link rel="preload" as="image" fetchpriority="high"
        href="<?= $seoEsc($heroImage) ?>"
<?php if ($heroSrcset !== ''): ?>
        imagesrcset="<?= $seoEsc($heroSrcset) ?>"
        imagesizes="100vw"
<?php endif; ?>
        >

  <!-- Schema.org Structured Data -->
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "NGO",
      "name": "Worldwide Education Foundation",
      "alternateName": "WEF",
      "url": "https://www.worldwideeducationfoundation.org",
      "logo": "assets/images/logo.png",
      "description": "WEF invests in the potential of children and youth across underserved communities in Central and South Asia through early development programs, English language learning, and scholarships.",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Chitral",
        "addressRegion": "Khyber Pakhtunkhwa",
        "addressCountry": "PK"
      },
      "foundingDate": "2021",
      "nonprofitStatus": "501(c)(3)",
      "taxID": "81-2135633",
      "areaServed": "Central and South Asia"
    }
  </script>
</head>

<body>

<?php $wefTagline = 'Providing the Seeds for Growth'; include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content">

    <!-- The page's single top-level heading.

         The hero rotates through nine project slides, and each carried its own
         level-1 heading — so this page announced nine different page titles to
         a screen reader, none of which was the site's actual name. Those nine
         are now paragraphs carrying the same .hero-glass-heading class, which
         renders identically: the universal reset zeroes margins and that class
         declares its own font, size, weight, line-height and colour, so nothing
         in the base heading rule was load-bearing.

         Visually hidden rather than removed, because the document still needs
         exactly one top-level heading and "Worldwide Education Foundation" is
         what it should say — not whichever project happens to be on screen.

         Written without literal tag syntax on purpose: an earlier version of
         this comment contained the markup it describes, and static scanners
         counted those as real headings. -->
    <h1 class="sr-only">Worldwide Education Foundation — Providing the Seeds for Growth</h1>

  <!-- ============================================
       HERO SECTION — Gradient + Liquid Glass Slideshow
       ============================================ -->
  <section class="hero-glass" id="home">
    <!-- Slideshow Background Images
         ------------------------------------------------------------------
         Only the first slide carries a real src. The other eight are held in
         data-src and fetched by initSlideshow() in js/main.js, one slide
         ahead of the rotation.

         This is not the same thing as loading="lazy", which was on some of
         these before and did nothing at all: every slide is absolutely
         positioned at inset:0 and hidden with opacity, so all nine are inside
         the viewport as far as the browser is concerned, and lazy loading
         only defers images that are OUTSIDE it. All nine — about 2.4 MB —
         were downloading on every homepage visit, competing for bandwidth
         with the one image the visitor could actually see.

         Slide 1 is the LCP element, so it is eager, fetchpriority="high" and
         decoded synchronously, and is additionally preloaded from <head>. -->
    <div class="hero-slideshow" id="hero-slideshow">
      <?php
      // Slides are edited in the admin under Home Slideshow. The four Ken
      // Burns moves cycle in the order the original nine slides used.
      $kenBurns = ['anim-zoom-in', 'anim-pan-left', 'anim-pan-right', 'anim-zoom-out'];
      foreach ($heroSlides as $i => $slide):
        $alt = trim((string) ($slide['image_alt'] ?? '')) ?: trim($slide['title_lead'] . ' ' . $slide['title_highlight']);
      ?>
      <div class="hero-slide<?= $i === 0 ? ' active' : '' ?> <?= $kenBurns[$i % 4] ?>">
        <?= imageTag($slide['image_src'], $alt, $i === 0 ? ['priority' => true, 'sizes' => '100vw'] : ['defer' => true, 'sizes' => '100vw']) ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Liquid Glass Content Panel -->
    <div class="container hero-glass-container">
      <div class="hero-glass-panel">

        <div class="hero-text-slider">
          <?php foreach ($heroSlides as $i => $slide):
            $lead = trim($slide['title_lead']) . ' ';
          ?>
          <div class="hero-text-slide<?= $i === 0 ? ' active' : '' ?>">
            <p class="hero-glass-heading">
              <span class="static-part" data-text="<?= e($lead) ?>"><?= e($lead) ?></span>
              <span style="white-space: nowrap;"><em class="highlight-part" data-text="<?= e($slide['title_highlight']) ?>"><?= e($slide['title_highlight']) ?></em></span>
            </p>
            <?php if (!empty($slide['description'])): ?>
            <p class="hero-glass-desc" style="margin-top: 1rem;">
              <?= e($slide['description']) ?>
            </p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="hero-glass-actions" style="margin-top: -1.5rem;">
          <a href="donate" class="button">
            <svg viewBox="0 0 16 16" class="bi bi-coin" fill="currentColor" height="16" width="16" xmlns="http://www.w3.org/2000/svg">
              <path d="M5.5 9.511c.076.954.83 1.697 2.182 1.785V12h.6v-.709c1.4-.098 2.218-.846 2.218-1.932 0-.987-.626-1.496-1.745-1.76l-.473-.112V5.57c.6.068.982.396 1.074.85h1.052c-.076-.919-.864-1.638-2.126-1.716V4h-.6v.719c-1.195.117-2.01.836-2.01 1.853 0 .9.606 1.472 1.613 1.707l.397.098v2.034c-.615-.093-1.022-.43-1.114-.9H5.5zm2.177-2.166c-.59-.137-.91-.416-.91-.836 0-.47.345-.822.915-.925v1.76h-.005zm.692 1.193c.717.166 1.048.435 1.048.91 0 .542-.412.914-1.135.982V8.518l.087.02z"></path>
              <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"></path>
              <path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11zm0 .5A6 6 0 1 0 8 2a6 6 0 0 0 0 12z"></path>
            </svg>
            Donate Now
          </a>
          <a href="active-projects" class="btn-glass-secondary" id="hero-explore-btn">
            Explore Projects
          </a>
        </div>
      </div>

      <!-- Slide Navigation Dots (Minimal Professional 3-Dot Indicator) -->
      <style>
        .hero-slide-dots {
          position: absolute;
          bottom: 28px;
          left: 50%;
          transform: translateX(-50%);
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 10px;
          z-index: 100;
          padding: 6px 14px;
          border-radius: 9999px;
          background: rgba(15, 23, 42, 0.45);
          backdrop-filter: blur(12px);
          -webkit-backdrop-filter: blur(12px);
          border: 1px solid rgba(255, 255, 255, 0.15);
          box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        .slide-dot {
          border: none;
          cursor: pointer;
          padding: 0;
          outline: none;
          display: inline-block;
          transition: all 0.3s ease;
        }

        .slide-dot.prev-dot, .slide-dot.next-dot {
          width: 7px;
          height: 7px;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.5);
        }

        @media (hover: hover) and (pointer: fine) {
          .slide-dot.prev-dot:hover, .slide-dot.next-dot:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: scale(1.2);
          }
        }

        .slide-dot.center-dot {
          width: 18px;
          height: 7px;
          border-radius: 9999px;
          background: #c69214;
          box-shadow: 0 0 8px rgba(198, 146, 20, 0.6);
        }

        /* Mobile: grow the tap area without changing how the dots look.
           Measured 7x7 and 18x7 CSS px on a phone — these are the only control
           for changing hero slides, and a 7px target is roughly a fifth of the
           44px guidance in each direction.

           The visible dot is unchanged: padding enlarges the button box while
           background-clip: content-box keeps the paint confined to the
           original 7px circle, and content-box sizing stops the padding from
           eating the declared width. Nothing here applies above 768px, so the
           desktop carousel is untouched.

           This override lives in this page rather than in css/styles.css
           because these dots are restyled by this inline <style> block, which
           comes after the stylesheet link and therefore wins at equal
           specificity — the same rule placed in styles.css had no effect. */
        @media (max-width: 768px) {
          .slide-dot {
            box-sizing: content-box;
            background-clip: content-box;
            /* 19px each side + the 7px dot = 45px tall, clearing the 44px
               guidance. 18px landed on 43 and missed it by one. */
            padding: 19px 14px;
          }

          /* The row is centred and absolutely positioned, so the extra padding
             would otherwise inflate the pill behind it. */
          .hero-slide-dots {
            padding: 0 4px;
            gap: 0;
          }
        }
      </style>
      <div class="hero-slide-dots" id="hero-slide-dots">
        <button class="slide-dot prev-dot" id="hero-prev-dot" aria-label="Previous Slide"></button>
        <button class="slide-dot active center-dot" id="hero-curr-dot" aria-label="Current Slide"></button>
        <button class="slide-dot next-dot" id="hero-next-dot" aria-label="Next Slide"></button>
      </div>
    </div>
  </section>

  <!-- ============================================
       CORE PURPOSE STATEMENT BANNER
       ============================================ -->
  <?php $purposeHero = pageHero('home'); // admin: Page Content -> Home -> Core Purpose background ?>
  <section class="purpose-section<?= $purposeHero['class'] ?>" id="vision-banner"<?= $purposeHero['style'] ?>>
    <?= $purposeHero['layers'] ?>
    <div class="purpose-overlay"></div>
    <div class="container purpose-container reveal">
      <span class="purpose-badge"><?= block('home', 'purpose_badge') ?></span>
      <h2 class="purpose-text"><?= blockRich('home', 'purpose_headline') ?></h2>
    </div>
  </section>

  <!-- ============================================
       ABOUT SECTION (Vision / Mission / Belief)
       ============================================ -->
  <section class="section about" id="about">
    <div class="container">
      <div class="section-header reveal">
        <h2><?= block('home', 'section1_title') ?></h2>
        <p><?= blockRich('home', 'section1_intro') ?></p>
      </div>

      <div class="about-grid">
        <!-- Vision -->
        <a href="our-mission" class="about-card reveal" id="about-vision">
          <div class="about-card-icon-container">
            <svg class="about-card-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </div>
          <h3><?= block('home', 'about_vision_title') ?></h3>
          <p><?= blockRich('home', 'about_vision_text') ?></p>
        </a>

        <!-- Mission -->
        <a href="our-mission" class="about-card reveal" id="about-mission" style="transition-delay: 0.15s;">
          <div class="about-card-icon-container">
            <svg class="about-card-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="22" y1="12" x2="18" y2="12"></line>
              <line x1="6" y1="12" x2="2" y2="12"></line>
              <line x1="12" y1="6" x2="12" y2="2"></line>
              <line x1="12" y1="22" x2="12" y2="18"></line>
              <circle cx="12" cy="12" r="6"></circle>
            </svg>
          </div>
          <h3><?= block('home', 'about_mission_title') ?></h3>
          <p><?= blockRich('home', 'about_mission_text') ?></p>
        </a>

        <!-- Belief -->
        <a href="our-mission" class="about-card reveal" id="about-belief" style="transition-delay: 0.3s;">
          <div class="about-card-icon-container">
            <svg class="about-card-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path
                d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .6 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5">
              </path>
              <line x1="9" y1="18" x2="15" y2="18"></line>
              <line x1="10" y1="22" x2="14" y2="22"></line>
            </svg>
          </div>
          <h3><?= block('home', 'about_belief_title') ?></h3>
          <p><?= blockRich('home', 'about_belief_text') ?></p>
        </a>
      </div>
    </div>
  </section>

  <!-- ============================================
       OUR ACTIVE PROJECTS — Preview (full projects on programs.html)
       ============================================ -->
  <section class="section projects" id="projects">
    <div class="container">
      <div class="section-header reveal">
        <h2><?= block('home', 'section2_title') ?></h2>
        <p><?= blockRich('home', 'section2_intro') ?></p>
      </div>

      <?php
      $programCards = publishedPrograms();
      ?>
      <div class="card-carousel">
        <button type="button" class="carousel-arrow carousel-arrow--prev" aria-label="Previous project">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="projects-grid card-carousel-track">
          <?php foreach ($programCards as $index => $program): ?>
            <?php
              $photo = uploadUrl($program['image_path']);
              $delay = $index === 0 ? null : 0.1 + (($index - 1) * 0.05);
              $cardId = $index === 0 ? ' id="project-eclc"' : '';
              $stats = [];
              for ($n = 1; $n <= 3; $n++) {
                  $value = trim((string) $program["stat{$n}_value"]);
                  $label = trim((string) $program["stat{$n}_label"]);
                  if ($value !== '' || $label !== '') {
                      $stats[] = ['value' => $value, 'label' => $label];
                  }
              }
              // Pad to exactly 3 items for the layout
              while (count($stats) < 3) {
                  $stats[] = ['value' => '&nbsp;', 'label' => '&nbsp;'];
              }
            ?>
            <a href="<?= e($program['slug']) ?>" class="card uiverse-card reveal"<?= $delay === null ? '' : ' style="transition-delay: ' . rtrim(rtrim(number_format($delay, 2, '.', ''), '0'), '.') . 's;"' ?><?= $cardId ?>>
              <div class="top-section"<?php if ($photo !== null): ?> style="background-image: url('<?= e($photo) ?>');"<?php endif; ?>>
                <div class="border"></div>
                <div class="icons">
                  <div class="logo">
                    <span class="card-year">
                      <?php if (!empty($program['badge_text'])): ?>
                        <?= e((string) $program['badge_text']) ?>
                      <?php else: ?>
                        &nbsp;
                      <?php endif; ?>
                    </span>
                  </div>
                  <div class="social-media">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                  </div>
                </div>
              </div>
              <div class="bottom-section">
                <span class="title"><?= e($program['name']) ?></span>
                <p class="card-desc"><?= e($program['summary']) ?></p>
                <div class="row row1">
                  <?php foreach ($stats as $stat): ?>
                    <div class="item">
                      <span class="regular-text"><?= $stat['label'] === '&nbsp;' ? '&nbsp;' : e($stat['label']) ?></span>
                      <span class="big-text"><?= $stat['value'] === '&nbsp;' ? '&nbsp;' : e($stat['value']) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
        <button type="button" class="carousel-arrow carousel-arrow--next" aria-label="Next project">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
      </div>
    </div>
  </section>

  <!-- ============================================
       OUR SPONSORS (HONOR BOARD) SECTION
       ============================================ -->
  <section class="honor-board-section reveal" id="sponsors">
    <div class="container">
      <div class="honor-board-card">

        <!-- Honor Board Main Header Banner -->
        <?php
        // Mobile-only line-broken versions of two CMS headings. Each is keyed
        // on the exact current CMS text, so if an admin edits the heading the
        // edited text shows everywhere instead of this stale copy.
        $honorBoardTitle = block('home', 'honor_board_title');
        $hbMobileTitles = [
            'Honor Board - Circle of Friends - Chitral, Pakistan' => ['Honor Board', 'Circle of Friends, Chitral Pakistan'],
            'Sponsors of Bright Beginnings: Early Childhood Learning Centers' => ['Sponsor a Bright Beginning', 'Early Childhood Centers'],
        ];
        $hbHeading = function (string $html) use ($hbMobileTitles): string {
            $lines = $hbMobileTitles[html_entity_decode($html, ENT_QUOTES)] ?? null;
            if ($lines === null) {
                return $html;
            }
            return '<span class="hb-full">' . $html . '</span>'
                . '<span class="hb-mobile" aria-hidden="true"><span class="hb-l1">' . e($lines[0])
                . '</span><span class="hb-l2">' . e($lines[1]) . '</span></span>';
        };
        ?>
        <div class="honor-board-header">
          <h2><?= $hbHeading($honorBoardTitle) ?></h2>
        </div>

        <?php
        /**
         * The honor board, from the Sponsors module.
         *
         * This was 200 lines of hardcoded markup listing 3 groups and 20
         * sponsors, while the CMS held 47 sponsors across 4 groups that no
         * page ever read — editing a sponsor in the admin panel changed
         * nothing a visitor saw. The badges that existed only in this markup
         * were migrated into the CMS first (see
         * admin/database/import-honor-board.php), so nothing was lost when the
         * source of truth moved.
         *
         * Markup below is reproduced exactly from what was here: the same
         * .sponsor-group / .sponsor-group-header / .sponsors-grid /
         * .sponsor-tile structure, so no CSS changes.
         *
         * Groups with no sponsors are skipped rather than rendered as an empty
         * heading — the CMS currently holds one such group.
         */
        foreach (publishedSponsorGroups() as $sponsorGroup):
            if ($sponsorGroup['sponsors'] === []) {
                continue;
            }
        ?>
        <div class="sponsor-group">
          <div class="sponsor-group-header">
            <h3><?= $hbHeading(e($sponsorGroup['title'])) ?></h3>
            <?php if (trim((string) $sponsorGroup['description']) !== ''): ?>
              <p><?= richText($sponsorGroup['description']) ?></p>
            <?php endif; ?>
          </div>
          <div class="sponsors-grid">
            <?php foreach ($sponsorGroup['sponsors'] as $sponsor): ?>
              <div class="sponsor-tile" data-sponsor="<?= e($sponsor['name']) ?>">
                <span class="sponsor-name"><?= e($sponsor['name']) ?></span>
                <?php if (trim((string) $sponsor['badge_text']) !== ''): ?>
                  <span class="sponsor-badge-tag"><?= e($sponsor['badge_text']) ?></span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div> <!-- honor-board-card -->
    </div>
  </section>

  <!-- ============================================
       OUR PARTNERS SECTION (Logo Marquee)
       ============================================ -->
  <section class="section partners-section" id="partners">
    <div class="container">
      <div class="section-header reveal" style="text-align: center; margin-bottom: 0;">
        <h2
          style="font-size: var(--fs-xl); color: var(--clr-primary-dark); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0;">
          <?= block('home', 'partners_title') ?></h2>
      </div>

      <div class="partners-marquee reveal">
        <div class="partners-track">
          <?php
          // Logos are edited in the admin under Partner Logos. The set is
          // printed twice so the marquee can loop seamlessly; the second copy
          // is hidden from screen readers.
          $partnerLogos = publishedPartners();
          foreach ([false, true] as $isCopy):
            foreach ($partnerLogos as $partner):
              $abs = publicPath($partner['logo_src']);
              $size = is_file($abs) ? @getimagesize($abs) : false;
              $dims = $size ? ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"' : '';
              $img = '<img' . $dims . ' src="' . e($partner['logo_src']) . '" alt="' . ($isCopy ? '' : e($partner['name'])) . '"'
                  . ($isCopy ? ' aria-hidden="true"' : '') . ' loading="lazy" decoding="async">';
          ?>
          <div class="partner-logo"><?php if (!empty($partner['link_url'])): ?><a href="<?= e($partner['link_url']) ?>" target="_blank" rel="noopener"<?= $isCopy ? ' tabindex="-1" aria-hidden="true"' : '' ?>><?= $img ?></a><?php else: ?><?= $img ?><?php endif; ?></div>
          <?php endforeach; endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================
       PROJECT UPDATES SECTION — Grid Layout
       ============================================ -->
  <section class="section project-updates" id="project-updates">
    <div class="container">
      <div class="section-header reveal">
        <h2><?= block('home', 'section3_title') ?></h2>
        <p><?= blockRich('home', 'section3_intro') ?></p>
      </div>

      <!-- Tablets and desktop: a row of cards with arrows (all nine in a grid
           made this the longest section on the page). Phones keep their
           one-card view and the .mobile-updates-nav buttons inside the grid. -->
      <div class="updates-grid-container updates-carousel reveal">
        <button type="button" class="carousel-arrow carousel-arrow--prev" aria-label="Previous update">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="updates-grid">
            <?php
            /**
             * Project Updates, from the CMS.
             *
             * Nine cards were hardcoded here while project_updates sat empty —
             * the module had drag-to-reorder and Trash and controlled nothing.
             * The cards were imported first (see
             * admin/database/import-hardcoded-content.php), including the
             * category chip and READ MORE target, which needed two new columns.
             *
             * Markup is reproduced exactly: same .updates-card structure and
             * the same tag-<name> modifier class, so no CSS changes.
             */
            foreach (publishedUpdates() as $update):
              $updateTag = trim((string) $update['tag']);
              // A photo uploaded in the admin is stored as "updates/<file>" under
              // admin/uploads/; the imported cards point at assets/ directly.
              $updateImage = (string) $update['image_path'];
              if ($updateImage !== '' && !str_starts_with($updateImage, 'assets/') && !preg_match('#^https?://#i', $updateImage)) {
                  $updateImage = uploadUrl($updateImage);
              }
            ?>
            <div class="updates-card">
              <div class="updates-card-image">
                <img width="1024" height="1024" src="<?= e($updateImage) ?>"
                  alt="<?= e($update['title']) ?>" loading="lazy" decoding="async">
                <?php if ($updateTag !== ''): ?>
                  <span class="updates-card-tag tag-<?= e(strtolower($updateTag)) ?>"><?= e($updateTag) ?></span>
                <?php endif; ?>
              </div>
              <div class="updates-card-body">
                <h3><?= e($update['title']) ?></h3>
                <p><?= e($update['content']) ?></p>
                <?php if (trim((string) $update['link_url']) !== ''): ?>
                  <a href="<?= e($update['link_url']) ?>" class="updates-card-link">
                    <span>READ MORE</span>
                    <span class="arrow">&rarr;</span>
                  </a>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <!-- Mobile Updates Navigation (Hidden on Desktop) -->
          <div class="mobile-updates-nav">
            <button class="uiverse-nav-btn prev" id="mobile-updates-prev" aria-label="Previous Project">
              <div class="button-box">
                <span class="button-elem">
                  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"></path></svg>
                </span>
                <span class="button-elem">
                  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"></path></svg>
                </span>
              </div>
            </button>
            <button class="uiverse-nav-btn next" id="mobile-updates-next" aria-label="Next Project">
              <div class="button-box">
                <span class="button-elem">
                  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"></path></svg>
                </span>
                <span class="button-elem">
                  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"></path></svg>
                </span>
              </div>
            </button>
          </div>
        </div>
        <button type="button" class="carousel-arrow carousel-arrow--next" aria-label="Next update">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
      </div>
    </div>
  </section>

  <!-- ============================================
       WEF BEGINNINGS SECTION — Timeline
       ============================================ -->
  <section class="section beginnings" id="beginnings">
    <div class="container">
      <div class="section-header reveal">
        <h2><?= block('home', 'section4_title') ?></h2>
      </div>

      <div class="beginnings-timeline reveal">
        <!-- Step 1 -->
        <div class="timeline-step">
          <div class="step-badge">1</div>
          <div class="step-content">
            <p><?= blockRich('home', 'beginnings_step1') ?></p>
          </div>
        </div>

        <!-- Step 2 -->
        <div class="timeline-step">
          <div class="step-badge">2</div>
          <div class="step-content">
            <p><?= blockRich('home', 'beginnings_step2') ?></p>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="timeline-step">
          <div class="step-badge">3</div>
          <div class="step-content">
            <p><?= blockRich('home', 'beginnings_step3') ?></p>
          </div>
        </div>

        <!-- Step 4 -->
        <div class="timeline-step">
          <div class="step-badge">4</div>
          <div class="step-content">
            <p><?= blockRich('home', 'beginnings_step4') ?></p>
          </div>
        </div>
      </div>

      <!-- Featured Videos (Part of WEF Beginnings) — same source as
           video-gallery.php now (publishedVideos()), not a second hardcoded
           copy of the same 2 embeds. Capped at 2 to keep this section's
           original size; the full set lives on the Video Gallery page. -->
      <div class="video-grid reveal" style="margin-top: var(--space-24);">
        <?php foreach (publishedVideos(2) as $video): ?>
          <?php $safeVideoUrl = safeYoutubeUrl($video['youtube_url'] ?? null); ?>
          <?php if ($safeVideoUrl === null): continue; endif; ?>
          <div class="video-card">
            <div class="video-wrapper">
              <iframe src="<?= e($safeVideoUrl) ?>" title="<?= e($video['title']) ?>"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
            <div class="video-info">
              <h3><?= e($video['title']) ?></h3>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <!-- ============================================
       WEF GLOBALLY SECTION
       ============================================ -->
  <section class="section impact wef-globally" id="impact">
    <div class="container">
      <div class="globally-main-grid reveal">
        <div class="globally-text-side">
          <h2><?= block('home', 'globally_title') ?></h2>
          <p><?= blockRich('home', 'globally_text1') ?></p>
          <p><?= blockRich('home', 'globally_text2') ?></p>

          <!-- Globally Stats Grid (2x2) -->
          <div class="globally-stats-grid">
            <!-- Teachers -->
            <div class="impact-card reveal" id="impact-teachers">
              <div class="impact-card-icon"><img width="256" height="189" src="assets/icons/SVG/TEACHERS.png" alt="Teachers Icon" style="width: 1em; height: 1em;" decoding="async" loading="lazy"></div>
              <div class="impact-label">Teachers</div>
              <div class="impact-number" data-count="<?= (int) block('home', 'impact_teachers_count') ?>" data-suffix="+">0</div>
            </div>

            <!-- Years of Experience -->
            <div class="impact-card reveal" style="transition-delay: 0.1s;" id="impact-experience">
              <div class="impact-card-icon"><img width="256" height="184" src="assets/icons/SVG/Years%20of%20Experience.png" alt="Years of Experience Icon" style="width: 1em; height: 1em;" decoding="async" loading="lazy"></div>
              <div class="impact-label">Years of Experience</div>
              <div class="impact-number" data-count="<?= (int) block('home', 'impact_experience_count') ?>" data-suffix="+">0</div>
            </div>

            <!-- Students -->
            <div class="impact-card reveal" style="transition-delay: 0.2s;" id="impact-students">
              <div class="impact-card-icon"><img width="256" height="202" src="assets/icons/SVG/STUDENTS.png" alt="Students Icon" style="width: 1em; height: 1em;" decoding="async" loading="lazy"></div>
              <div class="impact-label">Students</div>
              <div class="impact-number" data-count="<?= (int) block('home', 'impact_students_count') ?>" data-suffix="+">0</div>
            </div>

            <!-- Learning Spaces -->
            <div class="impact-card reveal" style="transition-delay: 0.3s;" id="impact-spaces">
              <div class="impact-card-icon"><img width="256" height="168" src="assets/icons/SVG/LEARNING%20SPACES.png" alt="Learning Spaces Icon" style="width: 1em; height: 1em;" decoding="async" loading="lazy"></div>
              <div class="impact-label">Learning Spaces</div>
              <div class="impact-number" data-count="<?= (int) block('home', 'impact_spaces_count') ?>" data-suffix="+">0</div>
            </div>
          </div>
        </div>
        <div class="globally-map-side">
          <div class="globally-map-wrapper">
            <img width="1024" height="826" src="<?= e(blockImage('home', 'globally_map_image')) ?>" alt="WEF Globally & Global Virtual School Footprint Map"
              class="globally-map-img" id="globally-map-img" loading="lazy" decoding="async">
            <div class="map-caption">
              <svg class="map-caption-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35M11 8v6M8 11h6"/></svg> Click to enlarge map (Global Virtual School Footprint)
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Interactive ECD Centers Map Section -->
  <section class="section map-section reveal" id="centers-map">
    <div class="container">
      <div class="map-card">
        <div class="map-header">
          <h2><?= block('home', 'map_title') ?></h2>
          <?php // Page Content -> Home -> Interactive map. {count} becomes the live
                // number, which map.js updates as visitors filter. ?>
          <p><?= str_replace('{count}', '<strong id="legend-total-count">94</strong>', e(blockValue('home', 'map_intro'))) ?></p>
        </div>
        <div class="map-body">
          <div id="map" role="region" aria-label="Interactive map showing ECD center locations"></div>

          <div class="map-sidebar">
            <div id="map-no-results" class="map-no-results" hidden>
              No mapped centers found for <strong data-sponsor-name></strong> yet.
            </div>

            <!-- Map Controls: District (map.js reads the district from each
                 center's village name) -->
            <div class="map-controls-panel map-district-panel">
              <label for="district-select" class="donor-search-label">Filter by District:</label>
              <div class="donor-select-wrapper">
                <svg class="donor-select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z" />
                  <circle cx="12" cy="9.5" r="2.5" />
                </svg>
                <select id="district-select" class="donor-select-input" data-district-select>
                  <option value="All">All Districts</option>
                  <option value="Upper Chitral">Upper Chitral</option>
                  <option value="Lower Chitral">Lower Chitral</option>
                </select>
              </div>
            </div>

            <!-- Map Controls: Donor Search -->
            <div class="map-controls-panel">
              <label for="donor-select" class="donor-search-label">Filter by Sponsor:</label>
              <div class="donor-select-wrapper">
                <svg class="donor-select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                <select id="donor-select" class="donor-select-input">
                  <option value="All">All Sponsors</option>
                  <!-- Populated by JS -->
                </select>
              </div>
            </div>

            <!-- Category Filter Legend -->
            <div class="map-legend-filters">
              <div class="legend-filters-header">
                <span class="legend-filters-title">Filter by Category</span>
              </div>
              <!-- Phone-only compact equivalent of the category buttons below
                   (hidden on desktop); sits beside the sponsor filter. -->
              <div class="category-select-wrapper">
                <select id="category-select" class="category-select-input" aria-label="Filter by category">
                  <option value="All">All Centers</option>
                  <option value="Entrepreneurial Model">Entrepreneurial</option>
                  <option value="Government School">Government</option>
                  <option value="Private School">Private</option>
                  <option value="Semi Govt School">Semi Govt</option>
                </select>
              </div>
              <div class="legend-filters-grid">
                <button class="legend-filter-btn legend-filter-all legend-filter-active-all" id="legend-filter-all">
                  All Centers
                </button>
                <button class="legend-filter-btn" data-filter-category="Entrepreneurial Model">
                  <span class="legend-filter-dot" style="background: #0e611d;"></span>
                  <span class="legend-filter-label">Entrepreneurial Models</span>
                  <span class="legend-filter-count" id="legend-count-entrepreneurial-model">0</span>
                </button>
                <button class="legend-filter-btn" data-filter-category="Government School">
                  <span class="legend-filter-dot" style="background: #1a56db;"></span>
                  <span class="legend-filter-label">Government Schools</span>
                  <span class="legend-filter-count" id="legend-count-government-school">0</span>
                </button>
                <button class="legend-filter-btn" data-filter-category="Private School">
                  <span class="legend-filter-dot" style="background: #9333ea;"></span>
                  <span class="legend-filter-label">Private Schools</span>
                  <span class="legend-filter-count" id="legend-count-private-school">0</span>
                </button>
                <button class="legend-filter-btn" data-filter-category="Semi Govt School">
                  <span class="legend-filter-dot" style="background: #dc6803;"></span>
                  <span class="legend-filter-label">Semi Govt Schools</span>
                  <span class="legend-filter-count" id="legend-count-semi-govt-school">0</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!-- ============================================
       CONTACT
       ============================================ -->
  <section class="section contact" id="contact">
    <div class="container">
      <div class="section-header reveal">
        <h2><?= block('home', 'section5_title') ?></h2>
        <p><?= blockRich('home', 'section5_intro') ?></p>
      </div>

      <div class="contact-grid">
        <div class="contact-info reveal-left" id="contact-info">
          <h3><?= block('home', 'contact_connect_title') ?></h3>
          <p><?= blockRich('home', 'contact_connect_text') ?></p>

          <div class="contact-detail">
            <div class="contact-detail-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s7-7.58 7-12A7 7 0 0 0 5 10c0 4.42 7 12 7 12z"/>
                <circle cx="12" cy="10" r="2.5"/>
              </svg>
            </div>
            <div class="contact-detail-text">
              <h4>Address</h4>
              <p><?= e($wefSettings['contact_address'] ?? 'W159N4940 Graysland Drive, Menomonee Falls, WI 53051 United States') ?></p>
            </div>
          </div>

          <div class="contact-detail">
            <div class="contact-detail-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L14 13l5 2v4a2 2 0 0 1-2.18 2A16 16 0 0 1 2 6.18 2 2 0 0 1 4 4z"/>
              </svg>
            </div>
            <div class="contact-detail-text">
              <h4>Phone</h4>
              <p><?= e($wefSettings['contact_phone'] ?? '+1 (214) 502-8388') ?></p>
            </div>
          </div>

          <div class="contact-detail">
            <div class="contact-detail-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="M2 6l10 7 10-7"/>
              </svg>
            </div>
            <div class="contact-detail-text">
              <h4>Email</h4>
              <p><?= e($wefSettings['contact_email'] ?? 'info@wefdallas.org') ?></p>
            </div>
          </div>
        </div>

        <form class="contact-form reveal-right" id="contact-form"
              method="post" action="api/contact.php" novalidate>
          <input type="hidden" name="form_type" value="<?= e($formType) ?>">

          <!-- Live region for the JS path; server-rendered banner for the
               no-JS one. Same pattern as contact.php/volunteer.php. -->
          <div class="form-status<?= $formResult ? ' form-status--' . e($formResult['type']) : '' ?>"
               data-form-status role="status" aria-live="polite"<?= $formResult ? '' : ' hidden' ?>>
            <?= $formResult ? e($formResult['text']) : '' ?>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="contact-name">Full Name</label>
              <input type="text" id="contact-name" name="name" placeholder="Your full name"
                     maxlength="<?= SUBMISSION_MAX_NAME ?>" autocomplete="name" required>
              <p class="form-error" data-error-for="name" hidden></p>
            </div>
            <div class="form-group">
              <label for="contact-email">Email Address</label>
              <input type="email" id="contact-email" name="email" placeholder="your@email.com"
                     maxlength="<?= SUBMISSION_MAX_EMAIL ?>" autocomplete="email" required>
              <p class="form-error" data-error-for="email" hidden></p>
            </div>
          </div>
          <div class="form-group">
            <label for="contact-subject">Subject</label>
            <select id="contact-subject" name="subject" required>
              <option value="" disabled selected>Select a topic</option>
              <?php foreach (submissionSubjects($formType) as $value => $label): ?>
                <option value="<?= e($value) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="form-error" data-error-for="subject" hidden></p>
          </div>
          <div class="form-group">
            <label for="contact-message">Message</label>
            <textarea id="contact-message" name="message" placeholder="Tell us how we can help..." rows="5"
              maxlength="<?= SUBMISSION_MAX_MESSAGE ?>" required></textarea>
            <p class="form-error" data-error-for="message" hidden></p>
          </div>

          <!-- Honeypot — same as contact.php/volunteer.php/the footer newsletter
               form: hidden from sight and assistive tech, out of the keyboard
               order, so only a bot filling every field it finds trips it. -->
          <div class="form-hp" aria-hidden="true">
            <label for="contact-website">Leave this field empty</label>
            <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
          </div>

          <button type="submit" class="btn btn-accent" id="contact-submit"
            style="width: 100%; justify-content: center;">
            Send Message →
          </button>
        </form>
      </div>
    </div>
  </section>

  </main>

  <!-- ============================================
       FOOTER
       ============================================ -->
<?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <!-- ============================================
       LIGHTBOX
       ============================================ -->
  <div class="lightbox" id="lightbox">
    <button class="lightbox-close" id="lightbox-close" aria-label="Close lightbox">✕</button>
    <button class="lightbox-nav lightbox-prev" id="lightbox-prev" aria-label="Previous image">←</button>
    <!-- No src attribute: initGalleryLightbox() in main.js sets it when an
         image is opened. src="" is not an empty image — browsers resolve it
         against the document URL and request the PAGE again, which was a
         second full HTML download on every visit to a page carrying this
         markup, whether or not anyone opened the lightbox. -->
    <img id="lightbox-img" alt="Gallery image enlarged view" decoding="async">
    <button class="lightbox-nav lightbox-next" id="lightbox-next" aria-label="Next image">→</button>
  </div>

  <!-- JavaScript -->
  <!-- Leaflet itself is loaded on demand by map.js, not from a tag here. -->
  <script src="<?= asset('js/map.js') ?>" defer></script>
  <script src="<?= asset('js/main.js') ?>" defer></script>
</body>

</html>