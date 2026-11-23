<?php require_once __DIR__ . '/../includes/content-blocks.php'; ?>
<?php require_once __DIR__ . '/../includes/content.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>

<?php
  // Social-card and canonical metadata. Values mirror this page's own
  // <title> and description below, so the card and the page always agree.
  $seoTitle = 'Gallery — Worldwide Education Foundation';
  $seoDescription = 'Photo gallery showcasing moments of learning, growth, and transformation from WEF\'s projects across Chitral, Pakistan and Central Asia.';
?>
<?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <title><?= $seoEsc($seoTitle) ?></title>
  <meta name="description" content="<?= $seoEsc($seoDescription) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>
</head>
<body>
<?php include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content">

  <?php $pageHero = pageHero('gallery'); ?>
  <section class="page-hero<?= $pageHero['class'] ?>"<?= $pageHero['style'] ?>>
    <?= $pageHero['layers'] ?>
    <div class="container">
      <h1><?= block('gallery', 'page_title') ?></h1>
      <p><?= blockRich('gallery', 'intro') ?></p>
    </div>
  </section>

  <section class="gallery-page-content">
    <div class="container">
      <?php
      // Photos come from the CMS (admin → Gallery), each filed under a
      // project, plus every project's own cover photo - see galleryPhotos().
      $galleryPhotos = galleryPhotos();

      // Filter buttons: one per project that has photos, in the order the
      // photos first appear, with "General" (photos filed under no project)
      // last.
      $galleryProjects = [];
      foreach ($galleryPhotos as $photo) {
          $galleryProjects[$photo['project']] = ($galleryProjects[$photo['project']] ?? 0) + 1;
      }
      if (isset($galleryProjects['General'])) {
          $generalCount = $galleryProjects['General'];
          unset($galleryProjects['General']);
          $galleryProjects['General'] = $generalCount;
      }

      // Highlights opens on the first photo (Bright Beginnings, the first
      // project in WEF at a Glance); js/gallery.js then steps through the
      // projects in the same order.
      $highlight = $galleryPhotos[0] ?? null;
      ?>
      <?php if (count($galleryProjects) > 1): ?>
      <div class="gallery-filters" role="group" aria-label="Filter photos by project">
        <button type="button" class="gallery-filter is-active" data-gallery-filter="" aria-pressed="true">All Projects <span class="gallery-filter-count"><?= count($galleryPhotos) ?></span></button>
        <?php foreach ($galleryProjects as $projectName => $count): ?>
          <button type="button" class="gallery-filter" data-gallery-filter="<?= e($projectName) ?>" aria-pressed="false"><?= e($projectName) ?> <span class="gallery-filter-count"><?= (int) $count ?></span></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="gallery-grid">
        <?php if ($highlight !== null): ?>
          <div class="gallery-highlights" id="gallery-highlights" role="region" aria-roledescription="slideshow" aria-label="Gallery highlights">
            <img class="gallery-highlights-slide is-active" src="<?= e(uploadUrl($highlight['image_path'])) ?>"
                 alt="<?= e($highlight['caption']) ?>" width="600" height="600" decoding="async">
            <img class="gallery-highlights-slide" alt="" width="600" height="600" decoding="async" aria-hidden="true">
            <span class="gallery-highlights-badge">Highlights</span>
            <div class="gallery-highlights-caption" aria-live="polite">
              <strong data-highlight-project><?= e($highlight['project']) ?></strong>
              <span data-highlight-caption><?= $highlight['caption'] !== $highlight['project'] ? e($highlight['caption']) : '' ?></span>
            </div>
            <div class="gallery-highlights-controls">
              <button type="button" class="gallery-highlights-btn" data-highlight-prev aria-label="Previous highlight">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
              </button>
              <button type="button" class="gallery-highlights-btn" data-highlight-next aria-label="Next highlight">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
              </button>
            </div>
          </div>
        <?php endif; ?>

        <?php foreach ($galleryPhotos as $index => $item): ?>
          <?php
            // Staggered reveal, as before: 0.1s, then +0.05s each.
            $delay = 0.1 + ($index * 0.05);
          ?>
          <div class="gallery-item reveal" style="transition-delay: <?= rtrim(rtrim(number_format($delay, 2, '.', ''), '0'), '.') ?>s;" id="gallery-<?= $index + 1 ?>" data-project="<?= e($item['project']) ?>">
            <img src="<?= e(uploadUrl($item['image_path'])) ?>"
                 alt="<?= e($item['caption']) ?>" data-caption="<?= e($item['caption']) ?>"
                 width="300" height="300" loading="lazy" decoding="async">
            <div class="gallery-item-overlay">
              <span class="gallery-item-project"><?= e($item['project']) ?></span>
              <?php if ($item['caption'] !== $item['project']): ?>
                <span class="gallery-item-caption"><?= e($item['caption']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Lightbox -->
  <div class="lightbox" id="lightbox">
    <button class="lightbox-close" id="lightbox-close" aria-label="Close lightbox">✕</button>
    <button class="lightbox-nav lightbox-prev uiv-nav-btn" id="lightbox-prev" aria-label="Previous image">
      <span class="uiv-nav-btn-box">
        <span class="uiv-nav-btn-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path></svg></span>
        <span class="uiv-nav-btn-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path></svg></span>
      </span>
    </button>
    <!-- No src attribute: initGalleryLightbox() in main.js sets it when an
         image is opened. src="" is not an empty image — browsers resolve it
         against the document URL and request the PAGE again, which was a
         second full HTML download on every visit to a page carrying this
         markup, whether or not anyone opened the lightbox. -->
    <img id="lightbox-img" alt="Gallery image enlarged view" decoding="async">
    <button class="lightbox-nav lightbox-next uiv-nav-btn uiv-nav-btn--next" id="lightbox-next" aria-label="Next image">
      <span class="uiv-nav-btn-box">
        <span class="uiv-nav-btn-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path></svg></span>
        <span class="uiv-nav-btn-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path></svg></span>
      </span>
    </button>
  </div>

  </main>

<?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
  <script src="<?= asset('js/gallery.js') ?>" defer></script>
</body>
</html>
