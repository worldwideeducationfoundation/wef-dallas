# Known issues

Last updated: 2026-10-14. Ordered by how much they matter to the owner.

## Needs the owner's decision

1. **Learning-centre count.** The home page says "100+" learning spaces. The map
   holds **94** centres. Either add the missing centres in Map Centers, or change
   the wording. Do not change the number on the page without the owner's answer.
2. **Project name spelling.** The owner's request spelled the first project
   "Bright Beginnings: **Earily** Childhood Learning Centers" (twice). The site uses
   "Early" everywhere. Confirm which spelling is correct before printing or
   sharing it.
3. **`verify-centers.php` and `js/verifier.js`.** A public pin checker with no
   sign-in. It can write `storage/verification_audit.json` and overwrite the
   old map file. The deploy script leaves it out of the upload, but it still
   exists in the repository and runs on local XAMPP. The owner has not yet said
   whether to delete it.
4. **Child consent is not recorded in the system.** Each subject's consent to
   being named and to being identifiable is required by the owner's ethics
   rules, but the database has no field for it. It is kept outside the system.
5. **Temporary domain flagged by Chrome.** On the Hostinger temporary address,
   Chrome showed a "dangerous site" warning. The cause is not confirmed. It is
   most likely the shared temporary address and the lookalike check, not the
   site's code. Check the address in Google's Safe Browsing transparency
   report. Connecting the real domain is the lasting fix.

## Data and content

6. **The map falls back to an old file.** If `map_centers` is empty or the
   database is unreachable, `api/map-centers.php` returns the old
   `public/assets/data/locations_map1.geojson`. That file predates the database
   corrections, and emptying the table in the admin would bring the old
   coordinates back. Remove the fallback or keep the table non-empty.
7. **New uploads must be committed.** `public/admin/uploads/` is committed to git on
   purpose (it is the only backup of editor photos). Photos uploaded through the
   admin on the live site are not in git; they are kept only on the server until
   someone downloads them. Decide with the owner how to back them up.
8. **Live database holds only the import.** Everything changed through the
   admin after the import exists only on the live site. Do not re-import a local
   dump (see `DATABASE.md`).

## Security

9. **Styles still allow `'unsafe-inline'`.** Both Content-Security-Policies allow
   inline styles, because many pages use `style=""` attributes. Scripts are
   locked down; styles are not (see `SECURITY.md`).
10. **Live database password was pasted into a chat.** Change it in hPanel and
    update `config/config.php` on both the computer and the live site (see
    `TASKS.md`).
11. **Constant Contact on the live domain is unchecked.** Connecting to the
    mailing list must be tried once on the live address, with the redirect URI
    registered (see `API.md`).

## Code and design debt

12. **Too many `!important` declarations.** About 860 occurrences in
    `css/styles.css` alone. The project rule is no new ones (see `AGENTS.md`).
13. **Large stylesheet.** `css/styles.css` holds duplicate rules; the critical
    CSS is built from it by `extras/tools/build-critical-css.php`.
14. **Two taglines in the site header.** `index.php` and `our-team.php` use one,
    the other pages use another. The owner has not chosen one.
15. **Asset names with spaces and capitals.** For example `assets/icons/SVG/Cart Icons/`.
    Renaming means updating references in the code and `statIcon()`.
16. **Comments point to old paths.** `website/public/css/styles.css` (around line
    2326) and `extras/tools/responsive-audit.js` (line 4) refer to
    `docs/CONTEXT.md`. The file now lives at `extras/agent/docs/CONTEXT.md`.
    Those comments were not changed in the move, because the move did not touch code.
17. **Old reference documents.** `docs/PROJECT_DETAILS.md`, `docs/wef-context.md`
    and `docs/PROJECT_STRUCTURE.md` describe a site that no longer exists (a
    static site with no CMS). They are kept for history. Read `context.md` and
    `ARCHITECTURE.md` instead.
18. **Stray local file.** `temp_dom.html` in the repository root is ignored by git
    (`temp_*`). It is not part of the site. Delete it when convenient.
19. **Local PHP is 8.0; the host runs 8.2 or newer.** Syntax newer than 8.0 cannot
    be tested locally.

## Deployment

20. **Not yet connected to the real domain.** The site runs on a temporary
    Hostinger address. The real domain, SSL on it, and Search Console are still to do.
21. **Several commits are not pushed.** `main` is three commits ahead of
    `origin/main`. Nothing has been pushed; ask the owner before pushing.
22. **The pin checker is not in the upload, but the zip checker is only as good
    as its list.** `build-deploy.ps1` refuses a zip with known local-only files.
    New local-only files need adding to its exclude list.
