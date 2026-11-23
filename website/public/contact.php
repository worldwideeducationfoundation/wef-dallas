<?php
require_once __DIR__ . '/../includes/content-blocks.php';
// Contact details come from the Settings module rather than being repeated
// in markup on three pages. Fallbacks keep the page correct if the row is
// ever missing.
$wefSettings = siteSettings();
// Supplies the subject list the dropdown renders and the fixed messages the
// no-JS result banner picks from — see includes/form-submissions.php.
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
  $seoTitle = 'Contact Us — Worldwide Education Foundation';
  $seoDescription = 'Get in touch with Worldwide Education Foundation.';
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

  <?php $pageHero = pageHero('contact'); ?>
  <section class="page-hero<?= $pageHero['class'] ?>"<?= $pageHero['style'] ?>>
    <?= $pageHero['layers'] ?>
    <div class="container">
      <h1><?= block('contact', 'page_title') ?></h1>
      <p><?= blockRich('contact', 'intro') ?></p>
    </div>
  </section>

  <section class="section contact" id="contact" style="background: var(--clr-off-white);">
    <div class="container">
      <div class="contact-grid">
        <div class="contact-info reveal-left" id="contact-info">
          <h3><?= block('contact', 'connect_title') ?></h3>
          <p><?= blockRich('contact', 'connect_text') ?></p>

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

        <!-- method/action are real: with JavaScript off this posts normally and
             the endpoint redirects back here with ?form=<code>, which the
             banner above the fields renders. js/main.js upgrades the same form
             to a fetch() so nobody loses their place on the page. -->
        <form class="contact-form reveal-right" id="contact-form"
              method="post" action="api/contact.php" novalidate>
          <input type="hidden" name="form_type" value="<?= e($formType) ?>">

          <!-- Live region for the JS path; server-rendered banner for the
               no-JS one. One element serves both so there is only ever one
               place a result appears. -->
          <div class="form-status<?= $formResult ? ' form-status--' . e($formResult['type']) : '' ?>"
               data-form-status role="status" aria-live="polite"<?= $formResult ? '' : ' hidden' ?>>
            <?= $formResult ? e($formResult['text']) : '' ?>
          </div>

          <p class="form-required-note">All fields are required.</p>
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

          <!-- Honeypot. Hidden from sight AND from assistive technology, so a
               person never encounters it; a bot that fills every field it finds
               gives itself away. tabindex="-1" keeps it out of the keyboard
               order for the same reason. -->
          <div class="form-hp" aria-hidden="true">
            <label for="contact-website">Leave this field empty</label>
            <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
          </div>

          <button type="submit" class="btn btn-accent btn-block" id="contact-submit">
            Send Message →
          </button>
        </form>
      </div>
    </div>
  </section>

  </main>

<?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>
