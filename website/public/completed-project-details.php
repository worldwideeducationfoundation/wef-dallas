<?php
require_once __DIR__ . '/../includes/content-blocks.php';
require_once __DIR__ . '/../includes/content.php';

$slug = $_GET['slug'] ?? '';
$project = publishedCompletedProjectBySlug($slug);

if (!$project) {
  // An unknown slug used to emit a bare <h1> and a link — no doctype, no
  // charset, no stylesheet, no header or footer. A visitor following a stale
  // link got what looks like a broken server rather than a page of this site,
  // and a crawler got an untitled document. Same 404 status, but rendered as
  // part of the site.
  //
  // The link points at the extensionless URL: "completed-projects.php" would
  // have been 301-redirected by .htaccess, costing a needless round trip.
  http_response_code(404);
  $notFoundTitle = 'Project not found';
  // Restores the "Back to Completed Projects" link, which used to be hardcoded
  // inside the partial. It is opt-in now that the same partial also serves the
  // site-wide 404, where sending a visitor to completed projects would be a
  // non-sequitur.
  $notFoundBackLink = 'completed-projects';
  $notFoundBackLabel = 'Back to Completed Projects';
  require __DIR__ . '/../includes/layout/not-found.php';
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

  <?php
    // Social-card and canonical metadata. Values mirror this page's own
    // title and description below, so the card and the page always agree.
    //
    // Bug fixed 2026-09-23: these two lines used to build their value from a
    // short-echo template tag while already inside this same PHP block, so
    // that tag was never parsed as PHP - it was literal text assigned into
    // a single-quoted string. Every social share of any of these 16 pages
    // showed that broken literal text as the link preview title, on the
    // Open Graph and Twitter Card tags in head-meta.php that read these two
    // variables. (The visible page title and meta description tags a few
    // lines below were unaffected - they set their own values directly and
    // correctly, independent of these two.)
    // Plain concatenation, no e() here - head-meta.php escapes these itself
    // when it writes them into each meta tag; escaping twice would turn an
    // apostrophe in a title into a visibly broken entity.
    $seoTitle = $project['title'] . ' — Worldwide Education Foundation';
    $seoDescription = $project['summary'];
  ?>
  <?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <title><?= e($project['title']) ?> — Worldwide Education Foundation</title>
  <meta name="description" content="<?= e($project['summary']) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>

  <style>
    /* Premium Project Details Styling */
    .project-hero {
      position: relative;
      width: 100%;
      height: 60vh;
      min-height: 500px;
      display: flex;
      align-items: flex-end;
      background-color: var(--clr-gray-900);
      overflow: hidden;
      margin-top: var(--header-height);
    }

    .project-hero-bg {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-image: url('<?= e(uploadUrl($project['image_path'])) ?>');
      background-size: cover;
      background-position: center;
      opacity: 0.6;
      transition: transform 10s ease;
    }

    @media (hover: hover) and (pointer: fine) {
      .project-hero:hover .project-hero-bg {
        transform: scale(1.05);
      }
    }

    .project-hero-gradient {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(to top, rgba(18, 20, 23, 0.9) 0%, rgba(18, 20, 23, 0.3) 50%, rgba(18, 20, 23, 0.1) 100%);
    }

    .project-hero-content {
      position: relative;
      z-index: 10;
      width: 100%;
      padding-bottom: var(--space-18);
      color: var(--clr-white);
    }

    .ph-badge {
      display: inline-block;
      background: var(--clr-primary);
      color: var(--clr-white);
      padding: 6px 16px;
      border-radius: var(--radius-full);
      font-size: var(--fs-sm);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: var(--space-4);
    }

    .ph-title {
      font-size: clamp(2.5rem, 5vw, 4rem);
      font-family: var(--ff-heading);
      line-height: 1.1;
      margin-bottom: var(--space-6);
      max-width: 900px;
      color: var(--clr-white);
      position: relative;
    }

    .ph-title::after {
      content: '';
      position: absolute;
      bottom: -14px;
      left: 0;
      width: 80px;
      height: 5px;
      background: linear-gradient(90deg, var(--clr-primary), var(--clr-accent));
      border-radius: var(--radius-full);
    }

    .ph-meta {
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-6);
      font-size: var(--fs-md);
      color: rgba(255, 255, 255, 0.8);
      font-weight: 500;
    }

    .ph-meta-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Main Content Layout */
    .project-body {
      padding: var(--space-32) 0;
      background: var(--clr-off-white);
    }

    .pb-grid {
      display: grid;
      grid-template-columns: 1fr 350px;
      gap: var(--space-24);
      align-items: start;
    }

    @media (max-width: 992px) {
      .pb-grid {
        grid-template-columns: 1fr;
      }
    }

    .pb-main {
      font-size: var(--fs-lg);
      line-height: 1.8;
      color: var(--clr-gray-700);
    }

    .pb-main h3 {
      font-size: var(--fs-2xl);
      color: var(--clr-primary-dark);
      margin-top: var(--space-14);
      margin-bottom: var(--space-4);
      font-family: var(--ff-heading);
    }

    .pb-main p {
      margin-bottom: var(--space-6);
    }

    .pb-main ul {
      margin-bottom: var(--space-6);
      padding-left: var(--space-6);
      list-style-type: disc;
      color: var(--clr-gray-700);
    }

    .pb-main li {
      margin-bottom: var(--space-2);
    }

    /* Information Panel */
    .info-panel {
      background: var(--clr-white);
      border-radius: var(--radius-xl);
      padding: var(--space-14);
      box-shadow: var(--shadow-lg);
      border: 1px solid var(--clr-gray-200);
      position: sticky;
      top: calc(var(--header-height) + var(--space-10));
    }

    .ip-title {
      font-size: var(--fs-xl);
      font-family: var(--ff-heading);
      color: var(--clr-gray-900);
      margin-bottom: var(--space-10);
      padding-bottom: var(--space-2);
      border-bottom: 2px solid var(--clr-gray-200);
    }

    .ip-stat {
      margin-bottom: var(--space-6);
    }

    .ip-stat-value {
      font-size: var(--fs-3xl);
      font-family: var(--ff-heading);
      color: var(--clr-accent);
      line-height: 1;
      font-weight: 800;
    }

    .ip-stat-label {
      margin-bottom: 4px;
      font-size: var(--fs-sm);
      color: var(--clr-gray-500);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-weight: 600;
    }

    /* Gallery (Simulated) */
    .project-gallery {
      margin-top: var(--space-24);
    }

    .gallery-grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: var(--space-10);
      margin-top: var(--space-14);
    }

    @media (max-width: 768px) {
      .gallery-grid-2 {
        grid-template-columns: 1fr;
      }

      /* The sidebar stops being a sidebar once .pb-grid collapses to one
         column at 992px, so its sticky positioning has nothing to stick
         alongside — it just pins a tall panel over the content while
         scrolling. Released on small screens so it flows with the page. */
      .info-panel {
        position: static;
        top: auto;
      }

      /* Measured: this page overflowed by exactly 36px at every width tested,
         including 768. The cause was .reveal-right's translateX(60px) on
         .pb-sidebar — see the mobile block in css/styles.css, which switches
         those reveals to vertical travel. This guards the aside itself in
         case the shared rule is ever changed back. */
      .pb-sidebar {
        max-width: 100%;
      }
    }

    /* The 3-up gallery keeps repeat(3, minmax(300px, 1fr)) from its inline
       style attribute, which cannot fit below ~960px and is what forced the
       row wider than the phone. One column below 768. */
    @media (max-width: 768px) {
      /* !important is load-bearing here, not laziness: both grids carry
         grid-template-columns in an inline style attribute, and an inline
         style beats a stylesheet rule regardless of specificity. That is why
         the existing .gallery-grid-2 rule in the 768 block above never took
         effect. Both use minmax(300px, 1fr), whose fixed 300px lower bound is
         wider than the content box on a small phone. */
      .gallery-grid-2,
      .gallery-grid-3 {
        grid-template-columns: 1fr !important;
      }
    }

    .gallery-img {
      width: 100%;
      height: 250px;
      object-fit: cover;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      transition: transform var(--duration-base) var(--ease-smooth);
    }

    @media (hover: hover) and (pointer: fine) {
      .gallery-img:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
      }
    }
  </style>
</head>

<body>
  <?php include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content">

    <section class="project-hero">
      <div class="project-hero-bg"></div>
      <div class="project-hero-gradient"></div>
      <div class="container project-hero-content reveal">
        <span class="ph-badge">Completed Project</span>
        <h1 class="ph-title"><?= e($project['title']) ?></h1>
        <div class="ph-meta">
          <div class="ph-meta-item">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
              <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <?= e($project['location']) ?>
          </div>
          <div class="ph-meta-item">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            Completed in <?= e($project['completion_year']) ?>
          </div>
          <div class="ph-meta-item">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2">
              <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            <?= e($project['category']) ?>
          </div>
        </div>
      </div>
    </section>

    <section class="project-body">
      <div class="container">
        <div class="pb-grid">

          <div class="pb-main reveal">
            <!-- Format the description (convert newlines/markdown-ish to HTML) -->
            <?php
            // Simple parser for our mock description format
            $desc = e($project['description']);
            // Headings
            $desc = preg_replace('/### (.*)/', '<h3>$1</h3>', $desc);
            // Images
            // The text is already escaped, so the src cannot break out of the
            // attribute; it is still limited to this site's own uploads/assets
            // or an https:// address, so an editor cannot point it at a
            // javascript:/data: URL or at a tracking host over plain http. An
            // address outside that is shown as the alt text instead.
            $desc = preg_replace_callback('/!\[(.*?)\]\((.*?)\)/', static function (array $m): string {
                $src = trim($m[2]);
                if (!preg_match('#^(?!.*\.\.)(?:(?:admin/uploads|assets)/[A-Za-z0-9/_.%, -]+|https://[^\s"\'<>]+)$#', $src)) {
                    return $m[1];
                }
                return '<img src="' . $src . '" alt="' . $m[1] . '" loading="lazy" style="max-width: 100%; height: auto; border-radius: var(--radius-md); margin: var(--space-10) auto; display: block; box-shadow: var(--shadow-md);">';
            }, $desc);
            // Lists. Anchored to the start of a line (^, with the /m
            // multiline modifier) - without it, this matched "- " anywhere
            // in the text, so an ordinary date range like "September 2017 -
            // September 2018" got split into "September 2017" followed by a
            // stray <li>September 2018</li> bullet, mid-sentence.
            $desc = preg_replace('/^- (.*)$/m', '<li>$1</li>', $desc);
            // wrap consecutive LIs in UL
            $desc = preg_replace('/(<li>.*<\/li>(\r?\n)*)+/', '<ul>$0</ul>', $desc);
            // paragraphs
            $desc = nl2br($desc);
            // fix extra br tags around block elements
            $desc = preg_replace('/<br \/>\s*<h3>/', '<h3>', $desc);
            $desc = preg_replace('/<\/h3>\s*<br \/>/', '</h3>', $desc);
            $desc = preg_replace('/<br \/>\s*<ul>/', '<ul>', $desc);
            $desc = preg_replace('/<\/ul>\s*<br \/>/', '</ul>', $desc);
            ?>
            <p class="lead-text" style="font-size: 1.2em; color: var(--clr-primary-dark); font-weight: 500; margin-bottom: var(--space-14);">
              <?= e($project['summary']) ?>
            </p>

            <?= $desc ?>



          </div>

          <aside class="pb-sidebar reveal-right">
            <div class="info-panel">
              <h4 class="ip-title">Measurable Impact</h4>

              <div class="ip-stat">
                <div class="ip-stat-label"><?= e($project['stat1_label']) ?></div>
                <div class="ip-stat-value"><?= e($project['stat1_value']) ?></div>
              </div>

              <div class="ip-stat">
                <div class="ip-stat-label"><?= e($project['stat2_label']) ?></div>
                <div class="ip-stat-value"><?= e($project['stat2_value']) ?></div>
              </div>

              <div class="ip-stat" style="margin-bottom: 0;">
                <div class="ip-stat-label"><?= e($project['stat3_label']) ?></div>
                <div class="ip-stat-value"><?= e($project['stat3_value']) ?></div>
              </div>

            </div>
          </aside>

        </div>
        
        <!-- Full width gallery block -->
        <?php
        // Fallback to the main image if secondary is not provided yet
        $secondaryImage = uploadUrl(!empty($project['secondary_image_path']) ? $project['secondary_image_path'] : $project['image_path']);
        $mainImage = uploadUrl($project['image_path']);
        $tertiaryImage = uploadUrl($project['tertiary_image_path'] ?? null);
        $hasTertiary = $tertiaryImage !== null;
        $mapImage = uploadUrl($project['map_image_path'] ?? null);
        ?>

        <?php if ($mapImage !== null): ?>
          <div class="map-gallery-layout reveal" style="display: grid; gap: var(--space-10); margin-top: var(--space-32);">
            <style>
              @media (min-width: 768px) {
                .map-gallery-layout {
                  grid-template-columns: 2fr 1fr;
                }

                .map-gallery-layout .project-map {
                  margin: 0;
                  height: 100%;
                }

                .map-gallery-layout .project-map img {
                  height: 100% !important;
                  object-fit: cover !important;
                }

                .map-gallery-layout .gallery-side {
                  display: flex;
                  flex-direction: column;
                  gap: var(--space-10);
                }

                .map-gallery-layout .gallery-side .gallery-img {
                  height: auto;
                  flex: 1;
                  margin: 0;
                  object-fit: cover;
                }
              }
            </style>
            <div class="project-map">
              <img src="<?= e($mapImage) ?>" alt="Map of <?= e($project['title']) ?>" style="width: 100%; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);">
            </div>
            <div class="gallery-side">
              <img src="<?= e($mainImage) ?>" class="gallery-img" alt="Project image 1" decoding="async">
              <img src="<?= e($secondaryImage) ?>" class="gallery-img" style="transition-delay: 0.1s;" alt="Project image 2" decoding="async">
              <?php if ($hasTertiary): ?>
                <img src="<?= e($tertiaryImage) ?>" class="gallery-img" style="transition-delay: 0.2s;" alt="Project image 3" decoding="async">
              <?php endif; ?>
            </div>
          </div>
        <?php else: ?>
          <!-- Project Gallery -->
          <div class="gallery-grid-<?= $hasTertiary ? '3' : '2' ?>" style="margin-top: var(--space-32); display: grid; gap: var(--space-10); grid-template-columns: repeat(<?= $hasTertiary ? '3' : 'auto-fit' ?>, minmax(300px, 1fr));">
            <img src="<?= e($mainImage) ?>" class="gallery-img reveal" alt="Project image 1" decoding="async">
            <img src="<?= e($secondaryImage) ?>" class="gallery-img reveal" style="transition-delay: 0.1s;" alt="Project image 2" decoding="async">
            <?php if ($hasTertiary): ?>
              <img src="<?= e($tertiaryImage) ?>" class="gallery-img reveal" style="transition-delay: 0.2s;" alt="Project image 3" decoding="async">
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php $safeVideoUrl = safeYoutubeUrl($project['video_url'] ?? null); ?>
        <?php if ($safeVideoUrl !== null): ?>
          <div class="project-video-layout reveal" style="margin-top: var(--space-24); width: 100%;">
            <iframe width="100%" height="450" src="<?= e($safeVideoUrl) ?>" title="<?= e($project['title']) ?> video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen style="border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin-top: var(--space-24);"></iframe>
          </div>
        <?php endif; ?>

      </div>
    </section>

  </main>

  <?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
</body>

</html>