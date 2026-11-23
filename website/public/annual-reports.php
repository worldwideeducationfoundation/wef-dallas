<?php require_once __DIR__ . '/../includes/content-blocks.php'; ?>
<?php require_once __DIR__ . '/../includes/content.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>

<?php
  // Social-card and canonical metadata. Values mirror this page's own
  // <title> and description below, so the card and the page always agree.
  $seoTitle = 'Annual Reports — Worldwide Education Foundation';
  $seoDescription = 'Read our annual reports and see our financial and impact metrics.';
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

  <?php $pageHero = pageHero('annual-reports', 'padding-bottom: 2rem;'); ?>
  <section class="page-hero<?= $pageHero['class'] ?>"<?= $pageHero['style'] ?>>
    <?= $pageHero['layers'] ?>
    <div class="container">
      <h1><?= block('annual-reports', 'page_title') ?></h1>
      <p><?= blockRich('annual-reports', 'intro') ?></p>
    </div>
  </section>

  <section class="page-content" style="padding-top: 1rem; padding-bottom: 3rem;">
    <div class="container" style="max-width: 1000px; margin: 0 auto;">
      <div class="wef-doc-list">
        
        <?php
        /**
         * Annual reports, from the CMS.
         *
         * Two reports were hardcoded here while annual_reports sat empty — the
         * module could upload a PDF and nothing would appear on the site. Both
         * were imported first (admin/database/import-hardcoded-content.php) so
         * switching the source could not blank the page.
         *
         * Markup reproduced exactly, including the staggered reveal delay that
         * was an inline style on the second item — it is now derived from the
         * row's position so any number of reports animate in sequence.
         */
        $wefReports = publishedReports();
        ?>
        <?php if ($wefReports === []): ?>
          <div class="empty-state">Annual reports will be published here soon.</div>
        <?php endif; ?>
        <?php foreach ($wefReports as $reportIndex => $report): ?>
          <div class="wef-doc-item reveal"<?= $reportIndex > 0 ? ' style="transition-delay: ' . number_format($reportIndex * 0.1, 1) . 's;"' : '' ?>>
            <div class="wef-doc-info">
              <h3 class="wef-doc-title"><?= e($report['title']) ?></h3>
              <?php if (trim((string) $report['description']) !== ''): ?>
                <p class="wef-doc-meta"><?= e($report['description']) ?></p>
              <?php endif; ?>
            </div>
            <?php if (trim((string) $report['pdf_path']) !== ''): ?>
              <div class="wef-doc-actions">
                <a href="<?= e($report['pdf_path']) ?>" target="_blank" rel="noopener" class="wef-doc-btn view-btn">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  View
                </a>
                <a href="<?= e($report['pdf_path']) ?>" download class="wef-doc-btn download-btn">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                  Download
                </a>
              </div>
            <?php endif; ?>
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
