# Security

Last updated: 2026-10-14. The longer audit history is in
`docs/security.md`. This file describes what is in place now.

## What must never leave the server or the repository

| Item | Where | Why |
|---|---|---|
| Database password and user | `website/config/config.php` (gitignored) | Full read and write of the site |
| Constant Contact client secret | `config.php` only | Keeping it out of database backups stops a leaked backup posting to the mailing list |
| Constant Contact tokens | `constant_contact` table | They rotate, so they must be stored in the database |
| Database dumps and the upload zip | `extras/deploy/` (gitignored) | They hold the config, admin password hashes and every form submission |
| Form submissions | `form_submissions` table | Names, emails and messages of members of the public |

Rules that follow: never commit the items above, never paste them into a shared
document or chat, and never import a local dump over the live database.

## Private folders

- `website/includes/`, `website/config/` and `website/storage/` are refused
  over the web by `website/.htaccess`, before anything is forwarded to `public/`.
- `public/admin/includes/`, `public/admin/database/` and `public/admin/logs/`
  are refused by `public/admin/.htaccess`.
- Dotfiles and build or log extensions are refused.
- `/.git/` is refused. After deploying, check that `https://DOMAIN/.git/config`
  does not return 200.
- `verify-centers.php` is a public page with no sign-in. It is kept out of the
  upload by `build-deploy.ps1`. Do not add it back.

## Headers and the Content-Security-Policy

- Both `.htaccess` policies set a full header set, including
  `Strict-Transport-Security` on HTTPS.
- Scripts: both policies allow only `'self'` and the listed SHA-256 hashes.
  The public policy also allows `https://unpkg.com`, which the public map uses
  to load Leaflet. The hashes for the public site are in `includes/assets.php`.
- Styles: both policies keep `'unsafe-inline'`, because the pages carry many
  `style=""` attributes. Removing it is a large separate job; scripts were the
  priority.
- The public policy is set twice: as a `<meta>` tag in
  `includes/layout/head-meta.php` and as a header in `public/.htaccess`. Change
  both when the policy changes.
- Any change to an inline script changes its hash. Run
  `php extras/tools/csp-hashes.php` and update the header it prints.
- The admin policy allows images from the two map tile hosts only (Carto and Esri).

## Accounts and sessions

- Roles, from `public/admin/includes/auth.php`:
  - **Super admin:** everything. The `'*'` wildcard.
  - **Administrator:** all content, media, settings, activity, form
    submissions and newsletter.
  - **Editor:** content and media only. No form submissions and no settings.
- Every capability is listed in `ALL_CAPABILITIES`. Asking for an unlisted
  capability is an error, so a typo cannot silently grant access.
- Passwords: at least `PASSWORD_MIN_LENGTH` (12) characters, and not on the
  deny-list in `auth.php`.
- Login lockout: 5 failed attempts for one email in 15 minutes locks that
  email. Twenty failures from one IP in the window also block that IP. The
  `login_attempts` table records every attempt.
- Sessions: 1 hour idle timeout and 12 hours since sign-in, whichever is
  first. Each session re-reads its role and status every 5 minutes, so a
  disabled account is signed out within that time.
- Every state-changing admin form carries a CSRF token (`csrfField()`) and is
  checked with `requirePostWithCsrf()`. Log out is a POST form for the same reason.
- The session cookie is `HttpOnly` and `SameSite=Strict`, and `Secure` on HTTPS.
  The site detects HTTPS behind Hostinger's proxy through `X-Forwarded-Proto`.

## Uploads

- Only the listed types are accepted: JPG, PNG and WebP for images, PDF for
  reports, each with a size limit. The file's contents are checked, not only
  its name.
- JPEGs that carry camera metadata (including GPS position) are re-encoded by
  GD to remove it. If GD is missing, the file is kept as uploaded.
- PHP execution is disabled in `public/admin/uploads/`.
- Replacing or deleting an upload switches or removes every reference first,
  so no page is left pointing at a missing file.

## Debug output

- `APP_DEBUG_CONFIGURED` in the config only has effect on a local request
  (`includes/environment.php` checks the connection address and host).
  On the live domain, full errors are never shown. Errors go to the log files;
  visitors see a generic page.

## Known gaps

See `KNOWN_ISSUES.md`. The main ones: Constant Contact has not been tested
against the live domain; the login and upload flows have not been tested in a
browser on the live site.
