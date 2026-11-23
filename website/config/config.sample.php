<?php
/**
 * Copy this file to config.php and fill in real values.
 * config.php is gitignored — never commit real credentials.
 *
 * One config.php serves both places the site runs: the block below picks the
 * local or the live database by operating system, so the website/ folder is
 * uploaded to Hostinger unchanged. See extras/DEPLOY-HOSTINGER.md.
 */

// --- Database ---
if (PHP_OS_FAMILY === 'Windows') {
    // Local XAMPP
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'wef_admin');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Hostinger: the database created in hPanel -> Databases
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u000000000_wef');
    define('DB_USER', 'u000000000_wef');
    define('DB_PASS', 'CHANGE-ME');
    // Backups outside public_html (see "Backups" below).
    define('APP_BACKUP_DIR', dirname(__DIR__, 2) . '/wef-backups');
}
define('DB_CHARSET', 'utf8mb4');

// --- App ---
// Forces the Secure flag on the session cookie even when the request does not
// look like HTTPS to PHP. You normally do NOT need to set this to true:
// initSession() detects HTTPS from the request (including the
// X-Forwarded-Proto header that shared hosts send when TLS is terminated at a
// proxy) and sets Secure automatically. Leave it false unless you have a setup
// where that detection fails.
define('APP_FORCE_HTTPS_COOKIE', false);

// Base URL of this admin app, no trailing slash (used for redirects)
define('APP_BASE_URL', 'http://localhost/admin');

// --- Debug ---
// true shows full stack traces on screen. Safe to leave true here: this value
// is only a request, not the final say. includes/environment.php ANDs it with
// "is this host local", so on the live domain debug output stays off no matter
// what this file says. Errors always go to admin/logs/admin-error.log and
// storage/logs/site-error.log; the browser only ever gets a generic message.
//
// Renamed from APP_DEBUG to make that distinction explicit. The old name is
// still honoured, so an existing config.php keeps working — but the host gate
// applies either way.
define('APP_DEBUG_CONFIGURED', true);

// --- Constant Contact (newsletter) ---
// Connects the footer newsletter form to a Constant Contact mailing list, so
// an address typed there is added to the list immediately instead of waiting
// for somebody to copy it across from the CMS inbox.
//
// Entirely optional. With these unset the newsletter form behaves exactly as
// it did before: the address is stored in form_submissions and nothing is sent
// anywhere. Nothing on the public site errors or slows down.
//
// SETUP, once:
//   1. Sign in at https://developer.constantcontact.com -> My Applications ->
//      New Application. Any name; it is only shown to WEF staff.
//   2. Copy the API Key into CC_CLIENT_ID. Generate a secret ("App Secret")
//      and copy it into CC_CLIENT_SECRET -- it is shown once and cannot be
//      retrieved later, only regenerated.
//   3. Add the callback below to the app's "Redirect URI" list. It must match
//      CC_REDIRECT_URI character for character, including https:// and the
//      absence of a trailing slash. A mismatch is the usual reason Connect
//      ends on a Constant Contact error page.
//   4. In the CMS: Newsletter -> Connect to Constant Contact, sign in, then
//      choose which list signups join.
//
// The client secret stays here rather than in the database on purpose. The
// access and refresh tokens have to live in the database (they rotate, and
// this file is not writable), and database backups are downloadable from the
// CMS -- so keeping the secret out of them means a leaked backup cannot be
// used to post to the mailing list. See the note on the constant_contact table
// in admin/database/schema.sql.
//
// define('CC_CLIENT_ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
// define('CC_CLIENT_SECRET', '');
// define('CC_REDIRECT_URI', 'https://www.worldwideeducationfoundation.org/admin/modules/newsletter/callback.php');
//
// Locally under XAMPP the redirect URI is:
// define('CC_REDIRECT_URI', 'http://localhost/WEF/public/admin/modules/newsletter/callback.php');

// --- Donations (Stripe) ---
// The Donate page (public/donate.php) takes payments on Stripe Checkout.
// Without STRIPE_SECRET_KEY the page shows, but "Continue to payment" answers
// "We could not start the payment".
//
//   STRIPE_SECRET_KEY      Dashboard -> Developers -> API keys. sk_test_... to
//                          test, sk_live_... in production. Never commit it.
//   STRIPE_WEBHOOK_SECRET  whsec_... from the webhook endpoint
//                          https://<site>/api/stripe-webhook.php (event
//                          checkout.session.completed). REQUIRED: without the
//                          webhook the 12- and 24-month center plans never stop.
//                          Locally: stripe listen --forward-to
//                          http://localhost/WEF/api/stripe-webhook.php
//   DONATE_BASE_URL        Optional. The site's public address for Stripe's
//                          return links; defaults to the address the donor is on.
//
// Each can also come from an environment variable of the same name.
//
// define('STRIPE_SECRET_KEY', 'sk_test_...');
// define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
// define('DONATE_BASE_URL', 'https://www.worldwideeducationfoundation.org');

// --- Backups ---
// Where admin/database/backup.php writes, and where restore.php reads.
//
// Left undefined, backups go to storage/backups/ inside the site. That is
// guarded — storage/ carries "Require all denied" and a live fetch of a
// .tar.gz returns 403 — but guarded is not the same as unreachable. On
// Hostinger storage/ sits inside public_html, so the live block at the top
// sets APP_BACKUP_DIR to a folder beside public_html instead, which no web
// request can reach.

// --- Production notes ---
// Nothing below needs changing on Hostinger; recorded so the deploy is not
// guesswork:
//
//   0. The contents of website/ go into public_html as they are. The
//      website/.htaccess (then public_html/.htaccess) forwards every request
//      into public/ and refuses includes/, config/ and storage/, so the
//      document root does not need to move. Nothing names a folder: the same
//      files run at / on the domain and at /WEF/ under XAMPP.
//   1. HTTPS redirect and HSTS are handled in the .htaccess files and skip
//      localhost automatically, so no edit is needed here.
//   2. (No RewriteBase or ErrorDocument path to edit any more.)
//   3. Apache still advertises its version in the Server header. Removing it
//      needs `ServerTokens Prod` + `ServerSignature Off` in the server config,
//      which is not settable from .htaccess.
//   4. Point DB_USER at an account with only the privileges it needs. The
//      public site never writes, so a SELECT-only account for the read tier is
//      possible — see the note at the top of includes/content.php.
//   5. Set APP_BACKUP_DIR above. This is the one item in this list that is a
//      security control rather than a convenience.
//   6. The .htaccess rule blocking /.git/ relies on mod_alias (with a
//      mod_rewrite fallback). After deploying, confirm it works on the live
//      host: `curl -I https://DOMAIN/.git/config` must NOT return 200.
