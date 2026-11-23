<?php
/**
 * The layout every project page shares (bright-beginnings.php and the other
 * eight). Each page file only sets $projectPage and includes this.
 *
 *   cover         the project's cover photo, full width, with its name on
 *                 the left and a drawn line under it
 *   impact line   the project's headline numbers, label above each value;
 *                 the location is one of them (added if the stats lack it),
 *                 followed by the badge ("Since" / "2025")
 *   before/after  two photos with one slider between them (projects with
 *                 'before_after' set; a missing photo says "coming soon")
 *   sections      the page text, one section per "## " subheading, photo and
 *                 text alternating sides (a colour panel stands in for a
 *                 photo the project does not have yet); the project's video
 *                 (Video Gallery entry titled with its name) sits beside
 *                 the section about its impact
 *   carousel      the project's own photos, centre slide large, moving on
 *                 by itself; "More photos coming soon" fills a short row
 *   pager         previous / next project
 *
 * The name, location, badge, stats, cover photo, before/after pair and
 * photo set all come from admin -> Our Active Projects; the words from
 * admin -> Page Content. Colours and fonts are the site's own.
 *
 * $projectPage keys: slug, highlight (the words the line is drawn under),
 * before_after (bool), icon, image_alt, seo_title, seo_description,
 * fallback (location, badge, image, stats).
 */

require_once __DIR__ . '/../program-nav.php';
require_once __DIR__ . '/../project-pages.php';

$pp = $projectPage;
$slug = $pp['slug'];
$fallback = $pp['fallback'];

$program = publishedProgramBySlug($slug);
$backLink = programBackLink('active-projects');
$pagerLinks = programPagerLinks($slug);

$projectName = trim((string) ($program['name'] ?? '')) ?: (PROGRAM_JOURNEY_FALLBACK_NAMES[$slug] ?? $pp['highlight']);
$shortName = trim(explode(':', $projectName, 2)[0]);
$location = trim((string) ($program['location_text'] ?? '')) ?: $fallback['location'];
$badge = trim((string) ($program['badge_text'] ?? '')) ?: $fallback['badge'];
$coverImage = ($program ? uploadUrl($program['image_path']) : null) ?? $fallback['image'];
$stats = $program ? array_values(array_filter([
    ['value' => $program['stat1_value'] ?? null, 'label' => $program['stat1_label'] ?? null],
    ['value' => $program['stat2_value'] ?? null, 'label' => $program['stat2_label'] ?? null],
    ['value' => $program['stat3_value'] ?? null, 'label' => $program['stat3_label'] ?? null],
], fn($s) => $s['value'] !== null && $s['value'] !== '')) : $fallback['stats'];

// The location lives in the stats line, not on the cover: added first when
// the project's own stats do not already give it.
$hasLocation = (bool) array_filter($stats, fn($s) => strcasecmp(trim((string) $s['label']), 'Location') === 0);
if (!$hasLocation && $location !== '') {
    array_unshift($stats, ['value' => $location, 'label' => 'Location']);
}

// The badge ("Since 2025") joins the stats line too, right after the
// location: "Since" above "2025". A year is not counted up like a number.
if ($badge !== '') {
    $since = preg_match('/^since\s+(.+)$/i', $badge, $m)
        ? ['value' => $m[1], 'label' => 'Since', 'count' => false]
        : ['value' => $badge, 'label' => 'Status', 'count' => false];
    $at = 0;
    foreach ($stats as $n => $s) {
        if (strcasecmp(trim((string) $s['label']), 'Location') === 0) {
            $at = $n + 1;
            break;
        }
    }
    array_splice($stats, $at, 0, [$since]);
}

// The cover shows the project's own name (admin -> Our Active Projects),
// split at a colon into a main line and a smaller one:
// "Bright Beginnings" / "Early Childhood Learning Centers".
$title = projectTitleParts($projectName, $pp['highlight']);
$sections = projectSections($slug, $projectName, $shortName);
$photos = projectPhotos($program);
$slides = projectCarouselPhotos($coverImage, $photos, $projectName);

// Before/after slider right after the impact line, on the projects that
// transform a physical space ('before_after' in the page settings). Always
// drawn there: a side whose photo is not uploaded yet says so.
$compare = !empty($pp['before_after']) ? programCompareSlider($program, true) : '';

// The project's video (admin -> Video Gallery, titled with the project's
// name) goes beside the section about its impact, else the first section.
$video = projectVideo($projectName);
$videoSection = null;
if ($video !== null && $sections) {
    $videoSection = 0;
    foreach ($sections as $n => $s) {
        if (preg_match('/impact/i', $s['heading'])) {
            $videoSection = $n;
            break;
        }
    }
}

$seoTitle = $pp['seo_title'];
$seoDescription = $pp['seo_description'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/head-meta.php'; ?>
  <title><?= $seoEsc($seoTitle) ?></title>
  <meta name="description" content="<?= $seoEsc($seoDescription) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>
</head>
<body>
<?php include __DIR__ . '/site-header.php'; ?>

  <main id="main-content" class="project-page">

    <!-- COVER -->
    <section class="pp-cover">
      <div class="pp-cover-media">
        <?= imageTag($coverImage, $pp['image_alt'], ['priority' => true]) ?>
      </div>
      <span class="pp-cover-arc" aria-hidden="true"></span>
      <div class="container pp-cover-inner">
        <a href="<?= e($backLink['href']) ?>" class="pp-cover-back">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          <?= e($backLink['label']) ?>
        </a>

        <?php // Kept out of the per-character heading animation (main.js): it would
              // wrap the spaces between these three parts into blank lines. ?>
        <h1 class="pp-cover-title" data-no-text-animate>
          <?php if ($title['kicker'] !== ''): ?><span class="pp-cover-kicker"><?= e($title['kicker']) ?></span><?php endif; ?>
          <span class="pp-cover-name"><?= highlightHtml($title['main'], true) ?></span>
          <?php if ($title['sub'] !== ''): ?><span class="pp-cover-sub"><?= e($title['sub']) ?></span><?php endif; ?>
        </h1>
      </div>
    </section>

    <!-- IMPACT LINE -->
    <?php if ($stats): ?>
    <section class="pp-stats" aria-label="<?= e($shortName) ?> in numbers">
      <div class="container pp-stats-row">
        <?php foreach ($stats as $stat): ?>
          <?php
            $isNumber = (bool) preg_match('/^\D{0,2}[\d,.]+/', (string) $stat['value']);
            $countUp = $isNumber && ($stat['count'] ?? true);
          ?>
          <div class="pp-stat<?= $isNumber ? '' : ' pp-stat--text' ?>">
            <span class="pp-stat-label"><?= e((string) $stat['label']) ?></span>
            <strong class="pp-stat-value<?= $isNumber ? '' : ' is-text' ?>"<?= $countUp ? ' data-count-up' : '' ?>><?= e((string) $stat['value']) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- BEFORE & AFTER: two photos, one slider between them -->
    <?php if ($compare !== ''): ?>
    <section class="pp-compare">
      <div class="container">
        <div class="pp-center-head reveal">
          <span class="pp-eyebrow">See the Change</span>
          <h2 class="pp-heading">Before &amp; <span class="pp-hl">After</span></h2>
        </div>
        <div class="pp-compare-frame reveal"><?= $compare ?></div>
        <p class="pp-compare-hint">Drag the slider to compare.</p>
      </div>
    </section>
    <?php endif; ?>

    <!-- SECTIONS -->
    <?php $photoIndex = 0; ?>
    <?php foreach ($sections as $i => $section): ?>
      <?php
        $hasVideo = $i === $videoSection;
        $photo = $hasVideo ? null : ($photos[$photoIndex] ?? null);
        if ($photo !== null) {
            $photoIndex++;
        }
        $heading = $i === 0
            ? highlightHtml(splitHighlight($section['heading'], $pp['highlight']))
            : highlightHtml(splitHighlight($section['heading']));
      ?>
      <section class="pp-feature<?= $i % 2 ? ' pp-feature--flip' : '' ?>"<?= $i === 0 ? ' id="pp-about"' : '' ?>>
        <div class="container pp-feature-grid">
          <div class="pp-feature-media reveal">
            <?php if ($hasVideo): ?>
              <div class="pp-feature-video">
                <div class="video-wrapper">
                  <iframe src="https://www.youtube.com/embed/<?= e($video['id']) ?>" title="<?= e($video['title']) ?>" frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen loading="lazy"></iframe>
                </div>
              </div>
            <?php elseif ($photo !== null): ?>
              <figure class="pp-feature-photo">
                <img src="<?= e($photo['src']) ?>" alt="<?= e($photo['caption'] !== '' ? $photo['caption'] : $projectName) ?>" loading="lazy" decoding="async">
                <?php if ($photo['caption'] !== ''): ?><figcaption><?= e($photo['caption']) ?></figcaption><?php endif; ?>
              </figure>
            <?php else: ?>
              <div class="pp-feature-panel pp-feature-panel--<?= $i % 3 ?>" aria-hidden="true">
                <span class="pp-feature-panel-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <img src="<?= e($pp['icon']) ?>" alt="" width="96" height="96" decoding="async" loading="lazy">
                <span class="pp-feature-panel-label"><?= e($shortName) ?></span>
              </div>
            <?php endif; ?>
          </div>
          <div class="pp-feature-text reveal">
            <span class="pp-eyebrow"><?= e($section['eyebrow']) ?></span>
            <h2 class="pp-heading"><?= $heading ?></h2>
            <div class="pp-prose"><?= $section['html'] ?></div>
          </div>
        </div>
      </section>
    <?php endforeach; ?>

    <!-- PHOTO CAROUSEL -->
    <?php if ($slides): ?>
    <section class="pp-gallery" id="pp-photos">
      <div class="container">
        <div class="pp-center-head reveal">
          <span class="pp-eyebrow">Moments from the Field</span>
          <h2 class="pp-heading">Life at <span class="pp-hl"><?= e($shortName) ?></span></h2>
        </div>
      </div>
      <div class="pp-coverflow" data-coverflow role="region" aria-roledescription="carousel" aria-label="Photos of <?= e($shortName) ?>">
        <div class="pp-coverflow-stage">
          <?php foreach ($slides as $n => $slide): ?>
            <figure class="pp-coverflow-slide<?= $slide['src'] === null ? ' is-placeholder' : '' ?>" data-index="<?= $n ?>" aria-roledescription="slide" aria-label="<?= $n + 1 ?> of <?= count($slides) ?>">
              <?php if ($slide['src'] !== null): ?>
                <img src="<?= e($slide['src']) ?>" alt="<?= e($slide['caption']) ?>" loading="lazy" decoding="async">
                <figcaption><?= e($slide['caption']) ?></figcaption>
              <?php else: ?>
                <div class="pp-coverflow-soon">
                  <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="M21 16l-5-5-7 7"/></svg>
                  <span><?= e($slide['caption']) ?></span>
                </div>
              <?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>
        <?php if (count($slides) > 1): ?>
        <button type="button" class="pp-coverflow-arrow pp-coverflow-arrow--prev" data-coverflow-prev aria-label="Previous photo">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <button type="button" class="pp-coverflow-arrow pp-coverflow-arrow--next" data-coverflow-next aria-label="Next photo">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
        </button>
        <div class="pp-coverflow-dots" role="group" aria-label="Choose a photo">
          <?php foreach ($slides as $n => $slide): ?>
            <button type="button" class="pp-coverflow-dot" data-coverflow-dot="<?= $n ?>" aria-label="Photo <?= $n + 1 ?>"></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- SUPPORT BAND -->
    <section class="pp-support" aria-labelledby="pp-support-heading">
      <div class="container pp-support-row">
        <div>
          <h2 id="pp-support-heading">Support <?= e($shortName) ?></h2>
          <p>Choose an amount and continue to PayPal.</p>
        </div>
        <?php // The home page's Donate button (class "button"), coin icon included. ?>
        <a href="donate?program=<?= e($slug) ?>" class="button pp-support-button">
          <svg viewBox="0 0 16 16" fill="currentColor" height="16" width="16" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M5.5 9.511c.076.954.83 1.697 2.182 1.785V12h.6v-.709c1.4-.098 2.218-.846 2.218-1.932 0-.987-.626-1.496-1.745-1.76l-.473-.112V5.57c.6.068.982.396 1.074.85h1.052c-.076-.919-.864-1.638-2.126-1.716V4h-.6v.719c-1.195.117-2.01.836-2.01 1.853 0 .9.606 1.472 1.613 1.707l.397.098v2.034c-.615-.093-1.022-.43-1.114-.9H5.5zm2.177-2.166c-.59-.137-.91-.416-.91-.836 0-.47.345-.822.915-.925v1.76h-.005zm.692 1.193c.717.166 1.048.435 1.048.91 0 .542-.412.914-1.135.982V8.518l.087.02z"></path>
            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"></path>
            <path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11zm0 .5A6 6 0 1 0 8 2a6 6 0 0 0 0 12z"></path>
          </svg>
          Donate Now
        </a>
      </div>
    </section>

    <!-- PREVIOUS / NEXT PROJECT -->
    <nav class="pp-pager" aria-label="More projects">
      <div class="container pp-pager-row">
        <a href="<?= e($pagerLinks['prev']['slug']) ?>" class="pp-pager-link">
          <span class="pp-pager-label">&larr; Previous<span class="pp-pager-word"> project</span></span>
          <span class="pp-pager-name"><?= e($pagerLinks['prev']['name']) ?></span>
        </a>
        <a href="<?= e($pagerLinks['next']['slug']) ?>" class="pp-pager-link pp-pager-link--next">
          <span class="pp-pager-label">Next<span class="pp-pager-word"> project</span> &rarr;</span>
          <span class="pp-pager-name"><?= e($pagerLinks['next']['name']) ?></span>
        </a>
      </div>
    </nav>

  </main>

<?php include __DIR__ . '/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
  <script src="<?= asset('js/project-page.js') ?>" defer></script>
</body>
</html>
