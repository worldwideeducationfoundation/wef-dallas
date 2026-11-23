# WEF — Architecture

How the project is laid out and why. Written during the Phase 1 refactor
(30 July 2026). Update this file when structure changes, not just code.

## Three tiers, one enforced boundary

```
PUBLIC TIER                      SHARED READ LAYER            ADMIN TIER
22 pages at project root    ->   includes/               <-   admin/
                                   bootstrap.php              includes/  (kernel)
includes/layout/                   database.php               modules/   (11 CRUD modules)
  head-meta.php                    content.php                database/  (schema + migrations)
  site-header.php                  content-blocks.php         uploads/   (user content)
  footer-slim.php                                             logs/
  footer-full.php                config/config.php
```

The boundary is real, not just documented:

- **Public pages never include anything from `admin/`.** A bug on a public
  page cannot modify content or reach the admin session.
- **`content.php` is SELECT-only.** No function in it writes. Every query
  filters `status = 'active' AND deleted_at IS NULL`, so drafts and trashed
  rows are structurally invisible to visitors.
- **Escaping is the caller's job**, via `e()` / `richText()` / `block()`.
  Nothing in the read layer returns pre-rendered HTML.

## Public tier

Each page is a full HTML document that includes four shared partials from
`includes/layout/`. Before Phase 1 every page carried its own copy of the
header, navigation and footer — about 115 KB of duplicated markup across 22
files, which had already drifted (see "Drift found" below).

| Partial | Used by | Contents |
|---|---|---|
| `head-meta.php` | all 22 | CSP + `X-Content-Type-Options` + referrer meta, charset, viewport |
| `site-header.php` | all 22 | skip link, mobile overlay, header, 5-group nav, hamburger |
| `footer-slim.php` | 20 pages | copyright + social links |
| `footer-full.php` | index, our-team | about, quick links, pages, newsletter, legal, social |

`site-header.php` takes one optional variable, `$wefTagline`. Two pages
(`index.php`, `our-team.php`) use a different logo tagline from the other 20;
both are preserved exactly rather than one being silently chosen, because
which is correct is a content decision. **The site should settle on one.**

### Adding a page

1. Copy the nearest existing page as a starting point.
2. Keep the four `include` lines; write only the `<main>` content.
3. Add it to `sitemap.xml`.

That is the whole list as of Phase 3. It used to also require adding the slug to
two separate rewrite alternations in `.htaccess`; those are gone — routing now
works off "does `<slug>.php` exist in this folder", so a new page is routable the
moment the file is saved. See "Routing" below.

## Error handling

Both tiers log detail to a directory Apache denies and show the visitor a
generic page. They are deliberately separate files:

| Tier | Bootstrap | Log | Failure page |
|---|---|---|---|
| Public | `includes/bootstrap.php` | `storage/logs/site-error.log` | 503 + `Retry-After`, `noindex` |
| Admin | `admin/includes/errors.php` | `admin/logs/admin-error.log` | 500 with a timestamp reference |

`bootstrap.php` is required only by `content.php` and `content-blocks.php`,
which no admin script loads, so the two exception handlers never contend for
the same request. `APP_DEBUG` in `config/config.php` puts full detail back on
screen locally and defaults to `false` when undefined — a config that predates
these files stays production-safe rather than leaking.

The public handler buffers output (`ob_start`) so a mid-render failure can
discard the half-built page instead of appending a stack trace to it. 503 is
used rather than 500 because the realistic cause is a database that is
temporarily down, and 503 tells crawlers to return rather than drop the URL.

## Where things live

| Path | Holds | Web-reachable |
|---|---|---|
| `/*.php` | the 22 public pages | yes |
| `includes/` | shared read layer + layout partials | **no** (403) |
| `config/` | credentials (`config.php` is gitignored) | **no** (403) |
| `admin/includes/`, `admin/database/`, `admin/logs/` | kernel, migrations, logs | **no** (403) |
| `storage/` | private uploads and the site log | **no** (403) |
| `admin/uploads/` | CMS images and PDFs | yes, but PHP execution disabled |
| `css/`, `js/`, `assets/` | front-end assets | yes |
| `docs/`, `tools/` | documentation, dev scripts | blocked by extension deny-list |
| `api/` | reserved namespace, returns JSON 404 | yes |

`.htaccess` denies dotfiles, plus an extension deny-list
(`.bak .py .ps1 .sql .log .md …`) and a name prefix list
(`dump_ reconstruct_ scratch temp_ …`). That second pair exists because 79
development artifacts — including `index.html.bak`, the complete previous site
source — were being served as plain text from the web root.

## Outbound integrations

Exactly one, and it is worth naming as a category because for most of this
project's life the answer was "none": nothing left the server, so nothing could
be slow, unreachable, or hold a credential.

**Constant Contact** (`includes/constant-contact.php`) forwards footer
newsletter signups to a mailing list. Like `includes/form-submissions.php` it is
loaded by **both tiers** — the public endpoint pushes the signup, the admin
module manages the connection — so it depends on nothing but `db.php`: no
session, no auth, no `e()`. `ccLog()` reaches whichever logger the calling tier
happens to own.

```
footer form ──► api/contact.php ──► form_submissions (committed first)
                       │
                       └──► ccSyncSubmission() ──► api.cc.email/v3
                                   │                    │
                                   └── outcome written back onto the row
                                       (newsletter_synced_at / _sync_error)
```

Three properties hold that ordering together:

- **The database write happens first, always.** The row is the record of what
  the visitor asked for; the list membership is a consequence of it.
- **`ccSyncSubmission()` never throws.** A third party's outage must not turn a
  successful signup into a 503, so every failure path ends in a recorded reason.
- **The outcome lands on the row, not only in a log.** That is what lets the
  Submissions list show a badge and a retry button instead of the failure being
  something you find out about a month later.

The call is **synchronous**, inside the visitor's own POST, capped by
`CC_CONNECT_TIMEOUT + CC_TIMEOUT` (4s + 8s). That is a deliberate simple choice:
a background queue needs cron, a retry table and a way to notice the worker has
stopped, and this project has none of those. Moving the one `ccSyncSubmission()`
call in `api/contact.php` into a cron-drained queue is the upgrade path, and
nothing else has to change.

Admin surface: **Newsletter** in the sidebar (`admin/modules/newsletter/`),
gated on a `newsletter.manage` capability of its own. The OAuth mechanics — and
in particular why `callback.php` deliberately includes nothing and re-posts to
`exchange.php` — are documented in `docs/security.md` and at the top of those
two files. Diagnose from the CLI with `php tools/constant-contact-check.php`.

## Drift found and fixed in Phase 1

Duplicating the header across 22 files produced exactly the failure that
predicts: the copies stopped matching. All were repaired by adopting the
majority markup into the shared partial.

- **`index.php` had no skip link.** The other 21 pages did. The homepage was
  the one page a keyboard user could not skip past the nav on.
- **`index.php` was missing the "Impact Goals" nav link** that all 21 other
  pages carried, so the homepage's Impact dropdown was short one item.
- **7 pages had no social links** in the footer (annual-reports, contact,
  early-childhood, scholarships, success-stories, video-gallery, volunteer).
- **`index.php` was missing `<span id="newsletter-feedback">`**, which
  `js/main.js:574` looks up by id — so the newsletter result was never
  announced on the homepage.
- **3 pages pointed the logo at `./#home`** instead of `./`.
- **Two different taglines** were in use. Preserved, not resolved — see above.

## Known remaining debt

Tracked in `docs/WEF_WEBSITE_ANALYSIS_REPORT.md` and the Phase 1 audit:

- The codebase is **not in version control** — the largest single risk.
  There is no database or upload backup either.
- ~~No security headers at the server level~~ — done in Phase 2; both tiers now
  send a full header set from `.htaccess`. The CSP still allows
  `'unsafe-inline'`, which is the remaining piece (see `docs/security.md`).
- ~~Contact, volunteer and newsletter forms have no `action` and no handler.~~ —
  all three now post to `api/contact.php` and store to `form_submissions`.
  Newsletter signups are additionally forwarded to Constant Contact; see
  *Outbound integrations* below.
- `css/styles.css` is 6,817 lines with 89 `!important` and duplicated
  selector blocks; `js/main.js` is one 1,020-line file loaded everywhere.
- `index.php` renders 9 `<h1>` elements (one per hero slide).
- Asset filenames and two directories still use spaces and capitals
  (`assets/icons/SVG/Cart Icons/`, `assets/PDF/`); renaming means updating
  55+ references and `statIcon()` in `content.php`.

---

# Backend layering (Phase 3)

Added 31 July 2026. This is the shape the backend settled into; read it before
adding a module.

## What was deliberately NOT done

No `controllers/`, `models/`, `services/` directories, no ORM, no DI container,
no PSR-4 autoloader.

The reasoning is worth recording, because "add MVC" is the obvious instinct:

- **There are no classes to autoload.** The codebase is functions and flat
  scripts. A PSR-4 autoloader would have nothing to map until the code is
  rewritten into classes, so adding one first is ceremony, not structure.
- **The app already has a service layer**, it just is not called one.
  `processBulkAction()`, `processReorder()`, `processDelete()`, `handleFileUpload()`,
  `trashItems()` and the `listParams()`/`listWhere()` family are exactly the
  "one place that owns this operation" that a service class would be.
- **Moving 58 working files with no version control and no backup** is a bad
  trade for a directory layout. The duplication was real and worth removing;
  the folder names were not the problem.

What the phase did instead: find the code that was genuinely repeated, and put
it in one place.

## Layers as they actually exist

```
entry point        admin/modules/<module>/{index,form,save,delete,bulk,reorder}.php
                     |  requires ONE bootstrap, then declares its own access rule
                     v
bootstrap          admin/includes/bootstrap.php     session + auth + csrf + db
                     |
services           includes/trash.php    processDelete(), trashItems(), restoreItems()
                   includes/bulk.php     processBulkAction()
                   includes/reorder.php  processReorder()
                   includes/upload.php   handleFileUpload() and friends
                   includes/listing.php  listParams/listWhere/listOrderLimit
                     |
data access        includes/db.php       dbFetchOne/All/Column/Pairs, dbExecute,
                     |                   dbInsert, dbTransaction, dbIdPlaceholders
                     v
connection         includes/database.php getDb()  — one shared PDO
```

The public tier is the same idea with fewer layers: page → `includes/content.php`
(SELECT-only read layer) → `db.php` → `database.php`.

## What each module file looks like now

```php
<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';

requireCapability('content.delete');
processDelete('gallery');
```

`bootstrap.php` does the mechanical setup — requires `auth.php`, `csrf.php` and
`db.php`, then calls `initSession()`. It deliberately does **not** check a
capability, mirroring the rule already stated in `layout-header.php`: the access
decision stays visible in each page's own first lines, so a new page can never
end up protected (or unprotected) because of something happening out of sight.

## Helpers added

| Helper | Replaces |
|---|---|
| `requirePostWithCsrf($to)` | The POST-method + CSRF block copied into 16 handlers, error string and all |
| `postId()` / `queryId()` | `isset($_POST['id']) ? (int) $_POST['id'] : null`, which treated `"abc"` as `0` |
| `processDelete($type)` | Five 37-line delete handlers that were byte-identical once identifiers were normalised |
| `dbFetchOne()` etc. | The `prepare` → `execute` → `fetch` triple, 79 occurrences |
| `dbTransaction()` | Hand-written begin/commit/rollBack, and tolerates nesting |
| `dbIdPlaceholders()` | The `IN (...)` builder written three times, with the int cast built in |
| `trashLabel()` | Per-module display nouns, now data in `TRASH_TYPES` |

## Routing

`.htaccess` no longer lists the 22 page slugs. Two general rules replace an
alternation that appeared **twice** in `.htaccess` and again in `sitemap.xml` —
adding a page meant three synchronised edits, and missing one produced a silent
404.

The safety property of the old allow-list is preserved structurally rather than
by enumeration: both patterns (`^([a-z0-9-]+)$`) contain no slash, so a path
like `admin/login.php` cannot match them at all. `index` is excluded from both
rules so the homepage does not gain a third URL.

Uses `%{REQUEST_FILENAME}`, not `%{DOCUMENT_ROOT}`, so the rules keep working
unchanged when the site moves from `/WEF/` to a domain root.

## Numbers

| | Before | After |
|---|---|---|
| Files repeating the auth/csrf/initSession preamble | 55 | 0 |
| Files repeating the POST+CSRF guard block | 21 | 0 |
| Delete handlers (5 modules) | 183 lines | 45 lines |
| Slug lists to edit when adding a page | 3 | 0 |
