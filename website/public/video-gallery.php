<?php require_once __DIR__ . '/../includes/content-blocks.php'; ?>
<?php require_once __DIR__ . '/../includes/content.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>

<?php
  // Social-card and canonical metadata. Values mirror this page's own
  // <title> and description below, so the card and the page always agree.
  $seoTitle = 'Video Gallery — Worldwide Education Foundation';
  $seoDescription = 'Watch videos of our projects and impact in action.';
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

  <?php $pageHero = pageHero('video-gallery'); ?>
  <section class="page-hero<?= $pageHero['class'] ?>"<?= $pageHero['style'] ?>>
    <?= $pageHero['layers'] ?>
    <div class="container">
      <h1><?= block('video-gallery', 'page_title') ?></h1>
      <p><?= blockRich('video-gallery', 'intro') ?></p>
    </div>
  </section>

  <section class="page-content">
    <div class="container">
      <div class="video-grid reveal">
        <?php foreach (publishedVideos() as $video): ?>
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

  </main>

<?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>
