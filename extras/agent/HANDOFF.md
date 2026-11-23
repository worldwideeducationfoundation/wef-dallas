# Handoff

Last updated: 2026-10-16. Read this after `context.md`, and before changing code.

## Where things stand

- **Branch:** `main`, ahead of `origin/main`. Nothing pushed. Latest commits:
  `4376a6b` (donate page, usability fixes, project and home page layout),
  `261e606` (agent files).
- **Upload build:** `extras/deploy/wef-website-20261016-2043.zip` (hero
  changes and Pages On/Off included). The live database also needs
  `honor-board-update-20261014.sql`, `stat-labels-20261016.sql` and
  `page-switches-20261016.sql` imported (not the full `.sql`, which would
  replace content edited on the live site).
- **`styles.css` line endings:** stored with CRLF (mixed in older commits).
  Stage it with `git -c core.autocrlf=false add` or the diff shows the
  whole file.
- **Live site:** not yet connected to the real domain. It is on a temporary
  Hostinger address (`hostingersite.com`). Google's Safe Browsing shows a
  "dangerous" warning there; the cause is not confirmed (see `KNOWN_ISSUES.md` #5).
- **Older builds:** the `1157`, `1016` and `0922` zips are superseded; upload
  only the newest. Rebuild after any change.
- **Local services:** XAMPP's MySQL and Apache were stopped at the end of the
  last session. Start them from the XAMPP control panel before testing locally.

## Donate page (committed in `4376a6b`)

- New: `website/public/donate.php`, `website/public/js/donate.js`, a `Donate page`
  block in `styles.css` (critical CSS rebuilt). Changed: `donateUrl()` fallback,
  header and home buttons, project-page Support band, Settings hint, sitemap.
- Tested with Playwright (Edge): selection, presets, validation, widths 390/768/1280,
  CSP, no-JS fallback. Not tested: a real PayPal checkout.

## What changed in the last session

| Change | Where | Verified by |
|---|---|---|
| Deploy script leaves out the pin verifier and checks the zip | `extras/tools/build-deploy.ps1` | Built zip; listing checked for required and forbidden files |
| `noindex` on any host other than the real domain | `website/includes/layout/head-meta.php` | `curl` with the temporary and real host headers: temporary gets `noindex, nofollow`, real domain gets `index, follow` |
| Removed a duplicate `robots`/`canonical` on the home page | `website/public/index.php` | Same curl check |
| Live database name, user and password filled in | `website/config/config.php` (gitignored) | Zip listing shows the values (password not printed) |
| Uploaded photos committed (new programme pictures, replaced team photo) | `website/public/admin/uploads/` | `git status` clean afterwards |
| Agent files created; existing agent docs moved | `extras/agent/` | File listing. No code changed. |

The `extras/agent/docs/` move changes the paths of two files in `extras/`
(`extras/README.md`) only. Two code comments still refer to the old path (see
`KNOWN_ISSUES.md` #16).

## Test results

- **Lint:** `php -l` passed for `head-meta.php`, `index.php` and `config.php`.
- **CSP hashes:** `csp-hashes.php` reported both policies current after the
  `head-meta.php` and `index.php` edits. Re-run it after any inline script change.
- **Robots header:** verified with `curl` against localhost, as described above.
- **Not tested:** the temporary domain in a browser; the live database import;
  the admin on the live site; Constant Contact from the live domain.
- **Earlier sessions:** the admin sweep rendered every admin page with no PHP
  errors. The pin picker passed a browser test under the admin CSP (94 dots,
  click, drag, typing, no CSP violations). The public map endpoint returned 94
  active centres.

## Next steps, in order

1. Ask the owner the questions in `TASKS.md` (items 1 to 6).
2. Change the Hostinger database password (it was shared in a chat), then rebuild the zip.
3. Extract the `20261016-2043` zip over `public_html`, import the three update
   `.sql` files, and run the checks in `TESTING.md`.
4. Investigate the Chrome warning on the temporary address.
5. Connect the real domain when the owner is ready.

## Notes for the next session

- Build check: `build-deploy.ps1` prints a warning if `config.php` still has
  placeholder values. It was not printed for `20261016-2043`.
- If you change the public CSS, run `extras/tools/build-critical-css.php`.
- Do not commit `website/config/config.php` or `extras/deploy/`.
