<?php require_once __DIR__ . '/../includes/content-blocks.php'; ?>
<?php require_once __DIR__ . '/../includes/content.php'; ?>
<?php
// Supplies programHubParam(), which tags each card link with the hub it was
// clicked from so the programme page's back link returns here rather than to
// that programme's category page.
require_once __DIR__ . '/../includes/program-nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>

<?php
  // Social-card and canonical metadata. Values mirror this page's own
  // <title> and description below, so the card and the page always agree.
  $seoTitle = 'Our Projects — Worldwide Education Foundation';
  $seoDescription = 'Explore all 9 active projects by Worldwide Education Foundation — from Early Childhood Learning Centers to Teacher Training and Scholarships across Chitral, Pakistan.';
?>
<?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <title><?= $seoEsc($seoTitle) ?></title>
  <meta name="description" content="<?= $seoEsc($seoDescription) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>
  <?= asyncStylesheet('css/uiverse-cards.css') ?>
</head>
<body>
<?php include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content">

  <?php $pageHero = pageHero('programs'); ?>
  <section class="page-hero<?= $pageHero['class'] ?>"<?= $pageHero['style'] ?>>
    <?= $pageHero['layers'] ?>
    <div class="container">
      <h1><?= block('programs', 'page_title') ?></h1>
      <p><?= blockRich('programs', 'intro') ?></p>
    </div>
  </section>

  <section class="programs-page-content">
    <div class="container">
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
            <a href="<?= e($program['slug']) . programHubParam('active-projects') ?>" class="card uiverse-card reveal"<?= $delay === null ? '' : ' style="transition-delay: ' . rtrim(rtrim(number_format($delay, 2, '.', ''), '0'), '.') . 's;"' ?><?= $cardId ?>>
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

  </main>

<?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>
