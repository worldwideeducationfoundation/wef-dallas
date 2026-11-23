# Architecture

Last updated: 2026-10-14. Update this file when the structure changes.

## The repository

```
WEF/                            repository root
  .htaccess                     local development only: forwards /WEF/ into website/
  .claude/skills/               Claude Code skills (tool-managed; do not move)
  .gitignore
  README.md                     one-paragraph pointer to website/ and extras/
  extras/                       everything that is not the running site
    agent/                      coding-agent files (this folder)
      AGENTS.md                 rules every agent follows
      context.md, ARCHITECTURE.md, README.md, ... (see README.md here)
      docs/                     older reference docs (see docs/CONTEXT.md)
    tools/                      build and maintenance scripts
    deploy/                     build output (gitignored; holds secrets)
    DEPLOY-HOSTINGER.md         the upload guide for people
    README.md                   what is in extras/
    graphify-out/               generated code graph (gitignored)
    old-db-backups/             old database dumps (see its README)
  website/                      THE SITE. Only this folder is uploaded.
    .htaccess                   forwards requests into public/; refuses private folders
    public/                     the document root's contents: pages, assets, admin
    includes/                   PHP libraries (refused over the web)
    config/                     config.php with credentials (refused over the web)
    storage/                    private logs, backups, applications (refused over the web)
```

Only `website/` is deployed. Nothing in `extras/` is reachable on the server.

## Two ways the site is served

| Environment | Address | How requests reach `public/` |
|---|---|---|
| Local (XAMPP) | `http://localhost/WEF/` | Root `.htaccess` rewrites into `website/`, then `website/.htaccess` into `public/` |
| Hostinger | the domain root (now a temporary `hostingersite.com` address) | `public_html/.htaccess` (from `website/.htaccess`) forwards into `public/` |

`website/.htaccess` is the same file in both places. Nothing in the code names
a folder, so URLs such as `/our-team` work at any depth. Each page is found by
its file name: `/our-team` is `public/our-team.php`.

## The three layers of PHP

```
public/*.php           pages for visitors          -> includes/content.php (read only)
public/admin/          the admin panel             -> public/admin/includes/ (its own kernel)
public/api/            JSON and form endpoints      -> includes/ and admin/includes as needed
includes/              shared read layer and layouts (no admin code)
```

Rules that hold the boundary in place:

- Public pages never include anything from `public/admin/`.
- `includes/content.php` only reads. Every query filters
  `status = 'active' AND deleted_at IS NULL`, so drafts and trashed rows are
  invisible to visitors.
- Escaping is the caller's job, with `e()`.
- When MySQL is unreachable, the read functions fall back to
  `includes/static-data.php`, so the public site still renders.

## Public site

- Pages in `public/*.php`. Each includes shared partials from
  `includes/layout/`: `head-meta.php` (SEO, CSP meta), `site-header.php`,
  `footer-full.php`, `footer-slim.php`, `not-found.php` (the 404 view).
- **Project pages** (`bright-beginnings.php` and eight others) are thin: each
  sets one `$projectPage` array (slug, highlight, `before_after`, icon, image
  alt, SEO title and description, fallback). `includes/layout/project-page.php`
  renders the layout. `includes/project-pages.php` holds the helpers
  (`projectSections`, `projectPhotos`, `projectCarouselPhotos`,
  `projectVideo`).
- **Page copy** comes from `BLOCK_REGISTRY` in `includes/content-blocks.php`:
  every editable text or image has a default there. The database stores only
  the admin's overrides, so an empty table renders the defaults. Read through
  `blockValue()`, `blockRich()`, `blockBody()` or `blockImage()`.
- **Map:** `public/js/map.js` fetches `api/map-centers.php`, which returns
  GeoJSON from `map_centers` (active rows only).
- **Newsletter:** the footer form posts to `api/contact.php`, which stores the
  row in `form_submissions` first and then calls `includes/constant-contact.php`.
  The database write happens first; a third-party failure never fails the
  visitor's request.

## Admin panel

- `public/admin/includes/` is the kernel: `bootstrap.php`, `auth.php` (roles,
  capabilities, sessions, login throttling), `csrf.php`, `functions.php`,
  `layout-header.php` and `layout-footer.php` (sidebar and shell), `listing.php`
  (sort, filter, search, paging, list/grid), `trash.php`, `bulk.php`,
  `reorder.php`, `upload.php` (image and PDF handlers), `media-specs.php`
  (the recommended sizes shown on upload fields), `errors.php`.
- `public/admin/modules/<name>/` holds one folder per content type. The
  standard set of files is `index.php` (list), `form.php` (create and edit),
  `save.php` (validate and write), `delete.php`, `bulk.php`, `reorder.php`.
  Some modules add their own helpers, such as `_options.php` or `_photos.php`.
- Modules: activity, completed-projects, gallery, hero-slides,
  impact-milestones, map-centers, media, mission-projects, newsletter, pages,
  partners, programs, reports, settings, sponsors, submissions, success-stories,
  team, trash, updates, users, video-gallery.
- Media Manager (`modules/media/`) lists every file under `admin/uploads/`,
  tells which pages use each one, and replaces a file by switching every
  reference at once, then deleting the old file and its resized copies.
- Map Centers (`modules/map-centers/`) includes a Leaflet pin picker
  (`admin/assets/js/map-picker.js`, Leaflet vendored in
  `admin/assets/vendor/leaflet/`).

## Uploads

- Editors upload to `public/admin/uploads/<folder>/`. The `image-derivatives.php`
  helper creates resized and WebP copies with GD, and strips GPS data.
- PHP execution is disabled in `uploads/` by its own `.htaccess`.
- The uploads folder is committed to git on purpose: it is the only backup of
  editor photos.

## Security layers (summary; see SECURITY.md)

- Per-folder `.htaccess` files: the public policy in `public/.htaccess`, the
  stricter admin policy in `public/admin/.htaccess`.
- Content-Security-Policy with SHA-256 hashes for the inline scripts. The
  hashes are declared in `includes/assets.php` and checked by
  `extras/tools/csp-hashes.php`.

## Logging and errors

| Tier | Log file | Shown to the visitor |
|---|---|---|
| Public | `storage/logs/site-error.log` | a 503 page with `Retry-After`, marked `noindex` |
| Admin | `public/admin/logs/admin-error.log` | a 500 page with a reference number |

Admin actions are also written to the `activity_logs` table.

## Deployment

```
website/  ->  extras/tools/build-deploy.ps1  ->  extras/deploy/wef-website-*.zip   (upload, extract into public_html)
                                            ->  extras/deploy/wef-database-*.sql  (import in phpMyAdmin)
```

The script checks that the zip holds the files the site needs, and that it
holds none of the local-only files (the pin verifier, logs, backups). Details
are in `extras/DEPLOY-HOSTINGER.md`.

## Known structural debt

- Some CSS and JS files are large and carry duplicate rules (see
  `KNOWN_ISSUES.md`).
- Some asset file names contain spaces and capitals.
- Two taglines are used in the site header; the owner has not chosen one.
