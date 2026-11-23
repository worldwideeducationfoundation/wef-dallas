<?php require_once __DIR__ . '/../includes/content-blocks.php'; ?>
<?php require_once __DIR__ . '/../includes/content.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>

<?php
  // Social-card and canonical metadata. Values mirror this page's own
  // <title> and description below, so the card and the page always agree.
  $seoTitle = 'Success Stories — Worldwide Education Foundation';
  $seoDescription = 'Read our success stories and see the real impact of our education initiatives.';
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

  <?php $pageHero = pageHero('success-stories'); ?>
  <section class="page-hero<?= $pageHero['class'] ?>"<?= $pageHero['style'] ?>>
    <?= $pageHero['layers'] ?>
    <div class="container">
      <h1><?= block('success-stories', 'page_title') ?></h1>
      <p><?= blockRich('success-stories', 'intro') ?></p>
    </div>
  </section>

  <section class="page-content">
    <div class="container">
      <div class="card-carousel">
        <button type="button" class="carousel-arrow carousel-arrow--prev" aria-label="Previous story">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="stories-grid card-carousel-track">
        <?php foreach (publishedSuccessStories() as $story): ?>
          <?php $storyImage = uploadUrl($story['image_path'] ?? null); ?>
          <article class="story-card reveal">
            <?php if ($storyImage !== null): ?>
              <img src="<?= e($storyImage) ?>" alt="<?= e($story['title']) ?>" class="story-image" style="height: 220px; width: 100%; object-fit: cover;" loading="lazy" decoding="async">
            <?php else: ?>
              <div class="story-image-placeholder" style="height: 220px; background: linear-gradient(135deg, var(--clr-gray-100), var(--clr-gray-200)); display: flex; align-items: center; justify-content: center; color: var(--clr-gray-400); font-family: var(--ff-heading); letter-spacing: 1px; font-size: 14px; text-transform: uppercase;">Image Space</div>
            <?php endif; ?>
            <div class="story-content">
              <div class="story-meta">
                <?php if (!empty($story['tag'])): ?><span style="color: var(--clr-accent);"><?= e($story['tag']) ?></span><?php endif; ?>
                <?php if (!empty($story['tag']) && !empty($story['story_date'])): ?> &bull; <?php endif; ?>
                <?= e($story['story_date'] ?? '') ?>
              </div>
              <h2 class="story-title"><?= e($story['title']) ?></h2>
              <?php if (!empty($story['link_url'])): ?>
                <a href="<?= e($story['link_url']) ?>" class="story-read-more">READ MORE &raquo;</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
        </div>
        <button type="button" class="carousel-arrow carousel-arrow--next" aria-label="Next story">
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
