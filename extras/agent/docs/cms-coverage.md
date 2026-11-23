# WEF — What the CMS actually controls

> **Corrected — 2026-09-22.** The "orphaned module" finding below was true on
> 2 August 2026 and has since been fixed: Sponsors, Annual Reports, Settings
> and Project Updates are **all now read on the public site** —
> `publishedSponsorGroups()` at `index.php:579`, `publishedReports()` at
> `annual-reports.php:48`, `publishedUpdates()` at `index.php:664`, and
> `siteSettings()` at `footer-full.php:38` plus `contact.php`/`volunteer.php`.
> Kept below as historical record of the audit that got them fixed. See
> [`docs/CONTEXT.md`](CONTEXT.md) §9 and the "Hardcoded vs dynamic" findings
> there for the current, verified coverage gaps (the 9 program detail pages,
> `early-childhood`/`scholarships` category cards, and `completed-projects`).

Audit of 2 August 2026, prompted by the request to make all hardcoded page
content editable.

The short version: **the admin panel controls less of the public site than it
appears to.** Four modules have a complete editing UI, real data in the
database, and no public page that reads any of it. Fixing that is worth more
than converting page copy into text blocks, because the editing screens already
exist — they are simply wired to nothing.

---

## 1. Module → public page coverage

| Admin module | Table | Rows | Read by a public page? |
|---|---|---|---|
| Programs | `programs` | 10 | **Yes** — `index.php`, `active-projects.php` via `publishedPrograms()` |
| Gallery | `gallery_items` | 7 | **Yes** — `gallery.php` via `publishedGalleryItems()` |
| Team | `team_members` | 27 | **Yes** — `our-team.php` via `publishedTeamBySection()` |
| Page Content | `content_blocks` | — | **Yes** — 14 blocks across 7 pages |
| **Honor Board / Sponsors** | `sponsors`, `sponsor_groups` | **47 + 4** | **No** — `publishedSponsorGroups()` exists and nothing calls it |
| **Annual Reports** | `annual_reports` | 0 | **No** — `publishedReports()` exists and nothing calls it |
| **Settings** | `site_settings` | 1 | **No** — `siteSettings()` exists and nothing calls it |
| **Project Updates** | `project_updates` | 0 | **No** — no reader function was ever written |

### What this means in practice

- **Honor Board is the urgent one.** Someone has entered 47 sponsors across 4
  groups. None of them render: the honor board on `index.php` is hardcoded
  markup. Editing, reordering, adding or removing a sponsor in the CMS changes
  nothing a visitor sees, and the work already done there is invisible.
- **Annual Reports** links four PDFs hardcoded in `annual-reports.php`. Adding a
  report through the CMS does nothing; adding one to the site means editing PHP.
- **Settings** holds the contact email, phone, address and five social URLs.
  Every one of those is hardcoded — the address and phone in `contact.php`
  (lines 48 and 60), the email in three places.
- **Project Updates** has a full module including drag-to-reorder, and the
  home page's "Project Updates" section is static markup.

These were almost certainly caused by the same thing: the pages were rebuilt
(commit *"totally new webstie with admin panel"*, then *"Desktop version done"*)
and the CMS calls in the previous versions were not carried across. The same
rebuild is what orphaned 8 of the 18 content blocks — see §3.

### Recommended order

1. **Sponsors → `index.php`** — most existing data, most visible, entirely
   additive. `publishedSponsorGroups()` already returns groups with their
   sponsors nested, so this is a template change, not new query work.
2. **Settings → `contact.php` + footer** — smallest change, removes the risk of
   a phone number being right in one place and stale in another.
3. **Annual Reports → `annual-reports.php`** — needs the four existing PDFs
   imported as rows first, otherwise the page goes empty on switchover.
4. **Project Updates → `index.php`** — needs a `publishedUpdates()` reader
   written; the module and table are otherwise ready.

Each is independently shippable. None requires a schema change.

---

## 2. Completed Projects — the plan

### Where it is today

`includes/completed-projects-data.php` is a 301-line PHP array of **16
projects**, with a header comment that says exactly what was intended:

> *Mock database layer for Completed Projects. … Once a backend developer
> creates the real table in MySQL (e.g. `completed_projects`), they can simply
> replace the `$completedProjects` array with a PDO fetch call.*

`completed-projects.php` lists them; `completed-project-details.php` renders one
by `?slug=`. Adding or editing a completed project currently means editing PHP
and deploying.

### The key finding: it does not need a new table

The 16 entries use these fields:

| Field | Present in all 16 | Already a `programs` column? |
|---|---|---|
| `slug`, `title`, `summary`, `description`, `category`, `image_path` | yes | yes (`title` → `name`) |
| `stat1..3_value` / `stat1..3_label` | yes | yes |
| `location` | yes | yes (`location_text`) |
| `completion_year` | yes | **no** — `year_launched` exists, but this is the *end* year and holds `'Active'` on one row, so it is not an integer |
| `secondary_image_path` | 4 of 16 | no |
| `tertiary_image_path` | 1 of 16 | no |
| `map_embed` | 3 of 16 | no |
| `video_embed` | 1 of 16 | no |

So 13 of the 19 fields already exist on `programs`. Two options:

**Option A — extend `programs` (recommended).**
Add `'completed'` to the `category` enum, plus five columns:
`completion_year VARCHAR(20)`, `secondary_image_path`, `tertiary_image_path`,
`map_embed`, `video_embed`.

- Completed projects inherit the whole Programs module for free: list, search,
  filter, drag-to-reorder, bulk publish, soft delete, Trash, media handling.
- The public pages already have a reader — `publishedPrograms('completed')`.
- One content type instead of two means one place to look for "a project".
- Cost: five columns that are NULL for the 10 active programs, and a form that
  shows fields only relevant to one category.

**Option B — a separate `completed_projects` table and module.**
Cleaner schema; costs a duplicate of every screen and handler the Programs
module already has (roughly 8 files), which then has to be maintained twice.
Worth it only if completed projects diverge much further from active ones.

Option A is the recommendation. The extra columns are cheap; a second copy of a
CMS module is not.

### Steps

1. **Migration** — add the enum value and five columns in `migrate.php`
   (idempotent, same pattern as everything else there); mirror into
   `schema.sql`.
2. **Import script** — `admin/database/import-completed-projects.php`, CLI-only
   like the other scripts in that folder, reading the existing array and
   inserting 16 rows. Idempotent on `slug` so a re-run is safe. Keeps the array
   file until the import is verified, then deletes it.
3. **Programs form** — show the five new fields only when category is
   `completed`. `completion_year` stays a free-text field precisely because one
   row's value is `'Active'`.
4. **Public pages** — swap both files onto `publishedPrograms('completed')` and
   a `programBySlug()` lookup. Markup is unchanged; only the data source moves.
5. **Delete** `includes/completed-projects-data.php`.

### Two things to decide before starting

- **`map_embed` and `video_embed` contain raw HTML** (an `<img>` tag and an
  iframe). Storing raw HTML that is printed unescaped makes the Programs form a
  stored-XSS vector for any Editor. Recommendation: replace both with plain
  fields — `map_image_path` (an upload, like every other image) and
  `video_url` (a YouTube URL, with the iframe built in the template from an
  id extracted server-side). Neither of the three map rows needs arbitrary HTML;
  they are all a single `<img>`.
- **`description` uses `### ` for subheadings.** The new `body` block type added
  today (`renderBodyText()` in `includes/content-blocks.php`) uses `## `. Make
  the renderer accept both, or normalise during the import — otherwise the
  imported descriptions render their headings as literal `###` text.

---

## 3. Page content blocks — state and remaining work

### Fixed today

8 of the 18 registry blocks were **orphaned**: editable in Page Content, read by
no page. The 2025–26 page rebuild dropped the `block()` calls.

| Block | What happened | Resolution |
|---|---|---|
| `home.hero_eyebrow`, `home.honor_board_title` | calls dropped in rebuild | reconnected |
| `our-mission.page_title`, `our-mission.intro` | page became "WEF At A Glance"; defaults described deleted copy | reconnected, defaults corrected, `intro` → `subtitle` |
| `annual-reports.category_label`, `section_title`, `section_intro`, `empty_message` | described a section the rewrite removed | removed from the registry |

The registry is now **14 blocks, 0 orphaned**, verified by a cross-check of
every `block()` call against every registry entry.

### Also added today

A third block type, **`body`** — long-form copy with paragraphs, bullet lists
(`- `) and subheadings (`## `). It escapes first and adds structure afterwards,
so CMS text can never become live HTML; 12 cases including four XSS attempts
pass. This is what makes the program pages' article copy editable without one
textarea per paragraph.

### Remaining

~29,000 characters of heading and paragraph text across 24 pages are still in
markup. Measured:

| Page | Headings | Paragraphs | Chars |
|---|---|---|---|
| `index.php` | 45 | 46 | 8,292 |
| `model-schools.php` | 3 | 10 | 2,464 |
| `secondary-scholarships.php` | 2 | 10 | 2,412 |
| `bright-beginnings.php` | 7 | 8 | 2,259 |
| `learning-centers.php` | 2 | 5 | 2,093 |
| `language-labs.php` | 2 | 4 | 2,020 |
| …18 more | | | 9,668 |

Suggested granularity, to avoid a Page Content screen with 90 fields on it:

- **Per section**: heading + intro as `text` / `textarea` blocks.
- **Per article**: the whole run of prose as one `body` block.

That is roughly 90–110 blocks rather than 250, and every visible string still
becomes editable. Blocks carry an optional `group` key so the edit form can file
them under the section names a visitor sees.

**A caveat worth stating:** the 11 program detail pages
(`model-schools.php`, `language-labs.php`, `learning-centers.php`,
`bright-beginnings.php`, `rising-stars.php`, `teacher-training.php`,
`skills-development.php`, `research-hub.php`, `scholarships.php`,
`secondary-scholarships.php`, `early-childhood.php`) are hardcoded pages
describing programmes — which is what the `programs` table is for, and it
already has `description`. Converting them to blocks makes them editable;
migrating them into Programs makes them *manageable* (add a programme without a
new PHP file). The second is more work and the better end state. Worth deciding
before spending effort on the first.
