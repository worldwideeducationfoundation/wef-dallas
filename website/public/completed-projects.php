<?php require_once __DIR__ . '/../includes/content-blocks.php'; ?>
<?php require_once __DIR__ . '/../includes/content.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>

<?php
  // Social-card and canonical metadata. Values mirror this page's own
  // <title> and description below, so the card and the page always agree.
  $seoTitle = 'Completed Projects — Worldwide Education Foundation';
  $seoDescription = 'Explore the completed projects by Worldwide Education Foundation.';
?>
<?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <title><?= $seoEsc($seoTitle) ?></title>
  <meta name="description" content="<?= $seoEsc($seoDescription) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>
  <style>
    .projects-editorial-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
      gap: var(--space-14);
      margin-top: var(--space-10);
    }
    
    @media (max-width: 768px) {
      .projects-editorial-grid {
        grid-template-columns: 1fr;
      }
    }

    .editorial-card {
      display: flex;
      flex-direction: column;
      background: var(--clr-white);
      border-radius: var(--radius-xl);
      overflow: hidden;
      box-shadow: var(--shadow-md);
      transition: all var(--duration-base) var(--ease-smooth);
      text-decoration: none;
      color: inherit;
      border: 1px solid rgba(0,0,0,0.04);
      position: relative;
    }

    @media (hover: hover) and (pointer: fine) {
      .editorial-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-xl);
        border-color: rgba(0,0,0,0.08);
      }
    }

    .ec-image-wrapper {
      position: relative;
      width: 100%;
      padding-top: 65%; /* Elegant aspect ratio */
      overflow: hidden;
      background: var(--clr-gray-100);
    }

    .ec-image {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform var(--duration-slow) var(--ease-smooth);
    }

    @media (hover: hover) and (pointer: fine) {
      .editorial-card:hover .ec-image {
        transform: scale(1.06);
      }
    }

    .ec-badge {
      position: absolute;
      top: 1.25rem;
      left: 1.25rem;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      color: var(--clr-primary-dark);
      padding: 6px 14px;
      border-radius: var(--radius-full);
      font-size: var(--fs-xs);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      z-index: 2;
      box-shadow: var(--shadow-sm);
    }

    .ec-status {
      position: absolute;
      top: 1.25rem;
      right: 1.25rem;
      background: var(--clr-accent);
      color: var(--clr-white);
      padding: 6px 14px;
      border-radius: var(--radius-full);
      font-size: var(--fs-xs);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      z-index: 2;
      box-shadow: var(--shadow-sm);
    }

    .ec-content {
      padding: var(--space-6) var(--space-10) var(--space-10);
      display: flex;
      flex-direction: column;
      flex-grow: 1;
    }

    .ec-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: var(--fs-sm);
      color: var(--clr-gray-500);
      margin-bottom: var(--space-2);
      font-weight: 600;
    }

    .ec-location {
      display: flex;
      align-items: center;
      gap: 6px;
      color: var(--clr-primary);
    }

    .ec-title {
      font-size: var(--fs-xl);
      color: var(--clr-gray-900);
      margin-bottom: var(--space-2);
      line-height: 1.25;
      font-family: var(--ff-heading);
    }

    .ec-summary {
      font-size: var(--fs-base);
      color: var(--clr-gray-600);
      margin-bottom: var(--space-6);
      line-height: 1.6;
    }

    /* Equal-height cards keep the numbers level; the space goes above them,
       not as a tall gap under a short summary. */
    .ec-stats {
      margin-top: auto;
    }

    .ec-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: var(--space-2);
      padding-top: var(--space-6);
      border-top: 1px solid var(--clr-gray-200);
    }

    /* Label above its value, the labels level along the top. */
    .ec-stat {
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: flex-start;
    }

    .ec-stat strong {
      font-family: var(--ff-heading);
      font-size: var(--fs-lg);
      color: var(--clr-gray-900);
      line-height: 1.1;
    }

    .ec-stat span {
      margin-bottom: 2px;
      font-size: var(--fs-xs);
      color: var(--clr-gray-500);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-weight: 500;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content">

  <?php $pageHero = pageHero('completed-projects', 'padding-bottom: 2rem;'); ?>
  <section class="page-hero<?= $pageHero['class'] ?>"<?= $pageHero['style'] ?>>
    <?= $pageHero['layers'] ?>
    <div class="container">
      <h1><?= block('completed-projects', 'page_title') ?></h1>
      <p><?= blockRich('completed-projects', 'intro') ?></p>
    </div>
  </section>

  <section class="programs-page-content" style="padding-top: 1rem; padding-bottom: 6rem;">
    <div class="container">
      <div class="card-carousel">
        <button type="button" class="carousel-arrow carousel-arrow--prev" aria-label="Previous project">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="projects-editorial-grid card-carousel-track">

        <?php
          $completedProjects = publishedCompletedProjects();
          $delay = 0;
        ?>
        <?php foreach ($completedProjects as $project): ?>
          <a href="completed-project-details.php?slug=<?= e($project['slug']) ?>" class="editorial-card reveal" style="transition-delay: <?= $delay ?>s;">
            <div class="ec-image-wrapper">
              <div class="ec-badge"><?= e($project['category']) ?></div>
              <div class="ec-status">Completed</div>
              <img src="<?= e(uploadUrl($project['image_path'])) ?>" alt="<?= e($project['title']) ?>" class="ec-image" loading="lazy" decoding="async">
            </div>
            <div class="ec-content">
              <div class="ec-meta">
                <span class="ec-location">
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                  <?= e($project['location']) ?>
                </span>
                <span class="ec-year"><?= e($project['completion_year']) ?></span>
              </div>
              <h3 class="ec-title"><?= e($project['title']) ?></h3>
              <p class="ec-summary"><?= e($project['summary']) ?></p>
              
              <div class="ec-stats">
                <div class="ec-stat">
                  <span><?= e($project['stat1_label']) ?></span>
                  <strong><?= e($project['stat1_value']) ?></strong>
                </div>
                <div class="ec-stat">
                  <span><?= e($project['stat2_label']) ?></span>
                  <strong><?= e($project['stat2_value']) ?></strong>
                </div>
                <div class="ec-stat">
                  <span><?= e($project['stat3_label']) ?></span>
                  <strong><?= e($project['stat3_value']) ?></strong>
                </div>
              </div>
            </div>
          </a>
          <?php $delay += 0.1; if($delay > 0.4) $delay = 0; ?>
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
