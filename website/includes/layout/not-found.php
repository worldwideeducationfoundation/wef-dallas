<?php
/**
 * Shared "not found" body for public pages that look content up by slug.
 *
 * Reuses the same shell as every other page — head-meta, site-header,
 * footer-full — so a dead link lands somewhere that still looks like the site
 * and still has navigation out of it, rather than on a bare <h1> with no
 * stylesheet.
 *
 * The caller sets the status code and may set $notFoundTitle / $notFoundBody
 * before requiring this. Defaults cover the common case.
 *
 * Reuses .page-hero and .empty-state, which already exist — no new CSS.
 */

$notFoundTitle = $notFoundTitle ?? 'Page not found';
$notFoundBody = $notFoundBody ?? 'The page you were looking for is not here. It may have been moved, or the link that brought you here may be out of date.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
<?php include __DIR__ . '/head-meta.php'; ?>
  <title><?= e($notFoundTitle) ?> — Worldwide Education Foundation</title>
  <meta name="description" content="The requested page could not be found.">
  <meta name="robots" content="noindex, follow">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <link rel="stylesheet" href="css/styles.css?v=4.3">
</head>

<body>
<?php include __DIR__ . '/site-header.php'; ?>

  <main id="main-content">
    <section class="page-hero">
      <div class="container">
        <h1><?= e($notFoundTitle) ?></h1>
        <p><?= e($notFoundBody) ?></p>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <?php
        // Only shown when a caller asks for it. This partial was written for
        // completed-project-details.php and hardcoded a "Back to Completed
        // Projects" link, which is exactly right there and a non-sequitur on
        // the site-wide 404 that now also uses it — someone mistyping a URL
        // has no reason to be sent to completed projects.
        if (!empty($notFoundBackLink)):
        ?>
        <p>
          <a href="<?= e($notFoundBackLink) ?>" class="link-back">&larr; <?= e($notFoundBackLabel ?? 'Go back') ?></a>
        </p>
        <?php endif; ?>
        <p>
          Or start again from the <a href="./">home page</a>,
          browse <a href="active-projects">active projects</a>,
          or <a href="contact">get in touch</a> if you think something is missing.
        </p>
      </div>
    </section>
  </main>

<?php include __DIR__ . '/footer-full.php'; ?>

  <script src="js/main.js"></script>
</body>

</html>
