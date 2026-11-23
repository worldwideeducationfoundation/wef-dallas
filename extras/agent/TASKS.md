# Tasks

Last updated: 2026-10-14. Highest priority first. Move an item to **Done** with
its date and commit when it is finished.

## Waiting for the owner

1. Delete `verify-centers.php` and `website/public/js/verifier.js`? Asked
   twice, no answer yet. The map no longer depends on them.
2. Learning centres: the home page says "100+", the map has 94. Add centres or
   change the wording?
3. Project name: "Early" or "Earily" in "Bright Beginnings: … Learning Centers"?
4. Should the map fall back to the old GeoJSON file when the table is empty
   (see `KNOWN_ISSUES.md` #6)? Recommended: remove the fallback.
5. Approval to push the three unpushed commits, and to commit the agent-folder move.
6. Approval to change code: the comment paths in `styles.css` (around line 2326)
   and `responsive-audit.js` (line 4) still point at `docs/CONTEXT.md`.

## Next (needs the owner or a live session)

7. **Change the Hostinger database password.** It was shared in a chat. Update
   `website/config/config.php` (LIVE block) on this computer, rebuild the zip, and
   change it on the live site too.
8. **Upload and test on the temporary domain.** Build `1157` is the current
   package: upload the zip, import the `.sql` in phpMyAdmin, and run the checks
   in `TESTING.md` (Public site, Admin, Security spot checks). Record what happens
   in `HANDOFF.md`.
9. **Chrome "dangerous site" warning on the temporary address.** Check the
   address in Google's Safe Browsing transparency report. Decide whether to
   request a review or wait for the real domain.
10. **Connect the real domain** (Hostinger: Connect domain). Then install SSL on
    it, submit the sitemap to Google Search Console, and remove the temporary
    address from any links.
11. **Constant Contact on the live domain:** connect once and send a test signup.
12. **Browser test of the Media Manager and Map Centers** while signed in
    (upload, replace, delete; add a centre by clicking the map, then Draft it).
    These were tested through scripts only.
13. **Child consent field** (`BUSINESS_RULES.md`): decide whether consent should
    be recorded in the admin.
14. **Remove `temp_dom.html`** from the repository root (ignored by git; not part of the site).

## Later

15. Remove `'unsafe-inline'` from styles (`SECURITY.md`). Large: many `style=""` attributes.
16. Choose one header tagline (`index.php` and `our-team.php` use another).
17. Rename assets with spaces and capitals (update references and `statIcon()`).
18. Reduce `!important` and duplicate rules in `styles.css` (`KNOWN_ISSUES.md` #12, #13).
19. Back up uploads that are added on the live site (`KNOWN_ISSUES.md` #7).
20. Retire or mark the older `docs/` files the agent docs supersede
    (`PROJECT_DETAILS.md`, `wef-context.md`, `PROJECT_STRUCTURE.md`).

## Done (recent)

- **2026-10-14:** Donate page (`/donate`) with program selector, presets and
  PayPal continue; header and home Donate buttons point to it; Support band on
  project pages. Not committed. Needs a real PayPal test on the live site.
- **2026-10-14:** Agent files created in `extras/agent/`; existing agent docs and
  `AGENTS.md` moved there. No code changed.
- **2026-09-30:** Deployment package for Hostinger (`build-deploy.ps1`, zip checks,
  temporary-domain `noindex`, guide), live database settings filled in (`1157`
  build). Commits `ea34b1c`, `d85d889`.
- **2026-09-30:** Media Manager upload, replace and delete; Map Centers section with
  a pin picker; 94 centres in the database (`a323834`).
- **2026-09-29:** Project pages redesign, before/after sliders, project videos,
  own-photo carousels, renames, admin dashboard fix, list paging (`f9bf442` to `f74c9cf`).
- **2026-09-28:** Repository split into `website/` and `extras/` (`60545a0`).
- **2026-09-26:** Script `'unsafe-inline'` removed; security audit fixes (`edea41b`, `cc5692d`).
