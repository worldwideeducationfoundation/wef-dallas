<?php
/**
 * The site footer. Used by every public page.
 *
 * Was one of two: this one on index.php and our-team.php, and footer-slim.php
 * (a copyright line plus social icons) on the other 22. Two footers meant two
 * places to update a link and one of them always being forgotten, and it left
 * most of the site without the branding, navigation or legal statement the
 * home page carried. footer-slim.php is gone; this is the only footer.
 *
 * The newsletter form now posts to api/contact.php like the contact and
 * volunteer forms. Until this change it was decoration -- js/main.js showed
 * "You're subscribed" after a setTimeout and stored nothing -- which was
 * tolerable on two pages and would not have been on all 24.
 */

require_once __DIR__ . '/../form-submissions.php';

// siteSettings(), block()/blockRich() and e() live here. Required explicitly
// rather than assumed: five pages (early-childhood, impact-goals,
// scholarships, success-stories, video-gallery) never loaded
// content-blocks.php, so when this footer started calling siteSettings() they
// fataled on an undefined function. A partial has to declare what it uses —
// depending on the including page to have loaded it works right up until one
// page doesn't. content-blocks.php itself requires content.php, so both are
// covered by the one require.
require_once __DIR__ . '/../content-blocks.php';

/**
 * Social links come from the Settings module.
 *
 * The five URLs were hardcoded here and nothing read site_settings, so the
 * Settings screen had five fields that changed nothing. Each falls back to the
 * URL that was in the markup, so a missing or empty setting leaves the footer
 * exactly as it was rather than rendering a dead link.
 *
 * Order is fixed here rather than in the data: these are icons, and the icon
 * has to match the URL it sits on.
 */
$wefSettings = $wefSettings ?? siteSettings();
$wefSocial = [
    'facebook' => $wefSettings['facebook_url'] ?? 'https://www.facebook.com/WorldwideEducationFoundationWEF',
    'twitter' => $wefSettings['twitter_url'] ?? 'https://x.com/WEFglobaledu',
    'instagram' => $wefSettings['instagram_url'] ?? 'https://www.instagram.com/worldwideeducationfoundation/',
    'linkedin' => $wefSettings['linkedin_url'] ?? 'https://www.linkedin.com/company/worldwide-education-foundation',
    'youtube' => $wefSettings['youtube_url'] ?? 'https://www.youtube.com/@wefdallasorg',
];
?>
  <footer class="footer" id="footer">
    <div class="container">
      <div class="footer-grid">
        <!-- Branding is the first thing in the footer and is given its own
             emphasis class rather than inheriting the plain footer text size:
             on 22 of these pages the organisation's name previously appeared
             only inside a copyright line. -->
        <div class="footer-about">
          <?php // The tagline sits under the name, beside the logo, which spans both. ?>
          <div class="footer-logo footer-logo--lead">
            <img src="assets/images/logo.png" alt="Worldwide Education Foundation logo" width="84" height="84" loading="lazy" decoding="async">
            <div class="footer-logo-text">
              <span>Worldwide Education Foundation</span>
              <p class="footer-tagline"><?= block('footer', 'tagline') ?></p>
            </div>
          </div>
          <p><?= blockRich('footer', 'about_text') ?></p>
        </div>

        <div class="footer-links">
          <h4>Quick Links</h4>
          <a href="./#home">Home</a>
          <a href="./#about">About Us</a>
          <a href="./#impact">Our Impact</a>
          <a href="active-projects">What We Do</a>
          <a href="gallery">Gallery</a>
          <a href="./#contact">Contact</a>
        </div>

        <div class="footer-links">
          <h4>Pages</h4>
          <a href="our-team">Our Team</a>
          <a href="our-mission">Our Mission</a>
          <a href="active-projects">Active Projects</a>
          <a href="completed-projects">Completed Projects</a>
          <a href="gallery">Photo Gallery</a>
        </div>

        <div class="footer-newsletter">
          <h4><?= block('footer', 'newsletter_title') ?></h4>
          <p><?= blockRich('footer', 'newsletter_text') ?></p>
          <!-- A real form: method, action and a submit button, so it works with
               JavaScript off and the address actually reaches the CMS inbox. -->
          <form class="footer-newsletter-form" method="post" action="api/contact.php" novalidate>
            <input type="hidden" name="form_type" value="newsletter">
            <label class="sr-only" for="newsletter-email">Email address for newsletter</label>
            <input type="email" id="newsletter-email" name="email" placeholder="Your email address"
                   maxlength="<?= SUBMISSION_MAX_EMAIL ?>" autocomplete="email" required>
            <!-- Same honeypot as the contact and volunteer forms. -->
            <div class="form-hp" aria-hidden="true">
              <label for="newsletter-website">Leave this field empty</label>
              <input type="text" id="newsletter-website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <button type="submit" aria-label="Subscribe">&rarr;</button>
          </form>
          <span class="newsletter-feedback" id="newsletter-feedback" aria-live="polite"></span>
        </div>
      </div>

      <div class="footer-bottom">
        <!-- Legal block compacted, not trimmed: the 501(c)(3) status, the tax
             ID, the 100%-to-projects statement and the administrative-costs
             attribution are all still here, in one line each instead of a
             single four-sentence paragraph, so they can be read rather than
             scanned past. The inline margin style was replaced by a class. -->
        <div class="footer-bottom-info">
          <p class="footer-legal"><?= blockRich('footer', 'legal_text1') ?></p>
          <p class="footer-legal"><?= blockRich('footer', 'legal_text2') ?></p>
          <p class="footer-copyright">&copy; <?= date('Y') ?> Worldwide Education Foundation. All rights reserved.</p>
        </div>
        <div class="footer-social">
          <a href="<?= e($wefSocial['facebook']) ?>" aria-label="Facebook" target="_blank" rel="noopener noreferrer"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg></a>
          <a href="<?= e($wefSocial['twitter']) ?>" aria-label="X (Twitter)" target="_blank" rel="noopener noreferrer"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
          <a href="<?= e($wefSocial['instagram']) ?>" aria-label="Instagram" target="_blank" rel="noopener noreferrer"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>
          <a href="<?= e($wefSocial['linkedin']) ?>" aria-label="LinkedIn" target="_blank" rel="noopener noreferrer"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg></a>
          <a href="<?= e($wefSocial['youtube']) ?>" aria-label="YouTube" target="_blank" rel="noopener noreferrer"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M21.582 6.186a2.636 2.636 0 0 0-1.85-1.875C17.95 3.86 12 3.86 12 3.86s-5.95 0-7.732.451a2.636 2.636 0 0 0-1.85 1.875C2 7.994 2 12 2 12s0 4.006.418 5.814a2.636 2.636 0 0 0 1.85 1.875C6.05 20.14 12 20.14 12 20.14s5.95 0 7.732-.451a2.636 2.636 0 0 0 1.85-1.875C22 16.006 22 12 22 12s0-4.006-.418-5.814zM9.99 15.464V8.536L15.936 12z"/></svg></a>
        </div>
      </div>
    </div>
  </footer>
