# WEF — Database

State of the schema, how to back it up, and how to change it. Written at the
end of Phase 4 (31 July 2026).

## Backup — start here

This is the thing the project did not have. There was no dump, no schedule and
no restore procedure, while `admin/uploads/` held the only copy of every photo
an admin had ever uploaded.

```
php admin/database/backup.php                 # database + uploads
php admin/database/backup.php --db-only
php admin/database/backup.php --keep=14       # retention in days, default 30
```

Output goes to `storage/backups/`, which sits under `storage/`'s
`Require all denied` — verified returning **403** over HTTP.

**Cron on Hostinger** (nightly, 02:30):

```
30 2 * * * cd /home/USER/public_html && php admin/database/backup.php >> storage/logs/backup.log 2>&1
```

### Restoring

```
php admin/database/restore.php --file=wef-db-20260731-020000.sql.gz --into=wef_restore_test
```

`--into` is required and has no default. Restoring over the live database needs
`--i-understand-this-overwrites`, because the dump opens with `DROP TABLE` for
every table — verified that the guard refuses without the flag.

`restore.php` compares row counts against the live database automatically. The
drill is: restore into a scratch database, check the counts, drop the scratch.

### What was actually tested, not just written

- Dump taken with `mysqldump --single-transaction` (87.4 KB → 21.7 KB gzipped).
- Restored into a scratch database: **all 12 tables matched live row-for-row.**
- Uploads: 45/45 files archived, **including `admin/uploads/.htaccess`** — miss
  that and a restored site loses the rule that stops uploads executing as PHP.
- An extracted image was **byte-identical** to the original (md5 compared).
- The overwrite guard was confirmed to refuse.

### Portability notes

Shared hosting is inconsistent, so the script degrades rather than failing:

| Component | Preferred | Fallback |
|---|---|---|
| Dump | `mysqldump` | pure-PHP dumper (works where `exec()` is disabled) |
| Uploads | `ZipArchive` | `PharData` → `.tar.gz` → plain recursive copy |

This is not hypothetical: **this XAMPP install has no `ext-zip` at all.** The
first version of the script printed "uploads NOT backed up" and carried on —
i.e. the irreplaceable half would have been silently skipped. `PharData` is
core PHP and, unlike `Phar`, is not blocked by `phar.readonly`.

A backup on the same disk as the database protects against mistakes, not
against losing the disk. **Copy `storage/backups/` offsite.**

## Schema

13 tables, InnoDB, `utf8mb4_unicode_ci` throughout.

| Table | Rows | Purpose |
|---|---|---|
| `programs` | 10 | Projects. Public + admin. |
| `team_members` | 27 | Board, advisors, staff. |
| `sponsor_groups` / `sponsors` | 4 / 47 | Honor Board, two-level. |
| `gallery_items` | 7 | Photo gallery. |
| `annual_reports` | 0 | PDF reports. |
| `project_updates` | 0 | News/blog. |
| `form_submissions` | 0 | Contact, volunteer + newsletter enquiries. **Written from outside** — see below. |
| `content_blocks` | 0 | Editable copy, **overrides only**. |
| `site_settings` | 1 | Singleton, pinned by `CHECK (id = 1)`. |
| `constant_contact` | 1 | Singleton. OAuth connection to the newsletter provider — see below. |
| `admin_users` | 2 | CMS accounts. |
| `login_attempts` | 28 | Rate limiting, per-email **and** per-IP. |
| `activity_logs` | 133 | Audit trail. Unbounded — see retention. |

### `form_submissions`

The only table an **anonymous visitor** can insert into, via `api/contact.php`.
That shapes three decisions:

- **It is the second unbounded table.** `activity_logs` grows from admin
  activity, which is self-limiting; this one grows from the open internet.
  Nothing prunes it yet — see *Still open* in `admin-panel.md`.
- **`idx_submissions_rate (ip_address, created_at)`** backs the per-IP flood
  check (5 per 15 minutes), which counts rows in this table rather than keeping
  a separate throttle table that could drift from it.
- **`form_type` and `subject` are plain varchars, not enums.** Both are
  validated against `SUBMISSION_FORMS` in `includes/form-submissions.php`, so
  adding a topic to a form's dropdown is a one-line registry edit rather than an
  `ALTER TABLE` on a table already holding rows that use the old set.

`status` is a triage workflow (`new` / `read` / `replied` / `archived`), *not*
the `draft` / `active` publishing state the content tables use — nothing here is
ever rendered on the public site.

`newsletter_synced_at` / `newsletter_sync_error` record whether a signup reached
the Constant Contact mailing list. Both are NULL on contact and volunteer rows,
which are never pushed anywhere. On a newsletter row, exactly one is normally
set — a timestamp, or the reason it failed. **Both NULL on a newsletter row
means it was never attempted**, which is the correct state for signups collected
before the integration existed; the migration deliberately does not backfill
them with a timestamp, because that would claim they had been sent. The
Submissions detail page offers a retry for both the failed and the never-tried
case.

Named for the purpose rather than the vendor, so changing mailing-list provider
is a config change rather than an `ALTER TABLE` on a table already holding rows.

### `constant_contact`

Singleton, pinned by `CHECK (id = 1)` like `site_settings`. Holds the OAuth
access token, the rotating refresh token, the granted scopes, the chosen
`list_id`, and the last error for the admin screen to display.

`access_token` is `TEXT`, not `varchar`: Constant Contact issues JWTs comfortably
past 255 bytes.

**The client ID and secret are deliberately NOT in this table** — they live in
`config/config.php`. Backups are downloadable from the CMS, and a refresh token
in a dump is useless without the secret needed to redeem it. See
`docs/security.md` for the full reasoning before moving either value.

### Foreign keys

| Constraint | Rule | Why |
|---|---|---|
| `sponsors.group_id → sponsor_groups.id` | CASCADE | Deleting a group takes its sponsors. Note this fires only on a real `DELETE`; soft delete does the cascade explicitly in `trashItems()`. |
| `activity_logs.user_id → admin_users.id` | SET NULL | The audit trail must outlive the account it describes. |
| `content_blocks.updated_by → admin_users.id` | SET NULL | Same. |
| `form_submissions.handled_by → admin_users.id` | SET NULL | Who triaged an enquiry must survive their account being removed. |
| `constant_contact.connected_by → admin_users.id` | SET NULL | The connection outlives the admin who authorised it; removing their account must not tear down the mailing-list integration. |

## Indexes

Every content table carries two composites, because the two tiers ask different
questions:

- **`idx_<t>_public` — `(status, deleted_at, display_order, …)`**
  The visitor-facing read layer always filters `status='active' AND deleted_at IS NULL`.
  Leading with `status` lets the whole `WHERE` + `ORDER BY` come from the index.
- **`idx_<t>_admin` — `(deleted_at, display_order, …)`**
  The CMS list shows drafts too, so it filters only on `deleted_at`. Leading
  with `deleted_at` also serves the Trash page's `deleted_at IS NOT NULL`.

Column order is WHERE columns first, then ORDER BY columns in sort order — the
only arrangement that lets InnoDB satisfy both without a sort step.

### Measured effect

EXPLAIN across the 17 queries the site actually runs: **filesorts 10 → 6**.

The remaining six are the optimizer correctly preferring a scan on a table of
7–50 rows, not a missing index. Proven by cloning the schema into a scratch
database at realistic volume (20k gallery items, 10k sponsors, 200k activity
rows) and re-running with and without the Phase 4 indexes:

| Query | Without | With | |
|---|---|---|---|
| `activity_logs` recent 20 | **74.14 ms** (scans 199,348 rows + filesort) | **0.87 ms** (reads 20 via index) | **85× faster** |
| public sponsors | 32.71 ms, `ALL` + filesort | 26.83 ms, `ref` | ~18% |
| public gallery | 4.24 ms | 3.95 ms | marginal |
| admin gallery list (LIMIT 20) | 0.27 ms | 1.09 ms | **slower** |

Two honest caveats:

1. The `activity_logs` result is the one that matters — it is the only table
   that grows without bound, and 85× is the difference between a usable and an
   unusable Activity page at a million rows.
2. **The admin gallery list got slower at scale.** With both indexes available
   the optimizer picks `idx_gallery_admin` over the narrower
   `idx_gallery_listing`, which could stop after 20 rows. 1.09 ms vs 0.27 ms is
   irrelevant in absolute terms, but it is a real regression and worth knowing
   rather than glossing over.

### Redundancy removed

15 indexes dropped, each a **strict prefix** of one that remains — the only
condition under which dropping is provably free. Detected by comparing column
lists programmatically, not by eye. Two (`idx_content_blocks_page`,
`idx_sponsors_group`) had been redundant for some time; nothing had checked.

Dropping `idx_sponsors_group` is safe for the foreign key: InnoDB needs *an*
index beginning with the FK column, and `idx_sponsors_listing` does.

## Migrations

```
php admin/database/migrate.php --dry-run   # show what would change
php admin/database/migrate.php             # apply
```

Idempotent — every step checks `information_schema` before acting. Verified:
running it twice in a row reports `0 changes applied` the second time.

`CREATE INDEX IF NOT EXISTS` is deliberately not used: MariaDB supports it,
MySQL 8 does not, and this has to run on both.

If a table is missing because `migrate.php` hasn't been run yet, the admin
still loads: Trash and the Media Manager skip that table and log
`table '…' does not exist - run admin/database/migrate.php` to
`admin/logs/admin-error.log`.

### Filling empty tables (new install)

```
php admin/database/seed-from-static.php            # dry run: report only
php admin/database/seed-from-static.php --apply
```

Run once after `migrate.php` on a new database (e.g. Hostinger). Every public
page falls back to `includes/static-data.php` while its table is empty, so the
site looks complete while the admin shows "No items yet". Adding one item in
the admin then replaces the whole fallback list with that one item. This
script copies the fallback rows into every table that has **no rows at all**
and leaves any table with content alone, so it is safe to re-run. Rows keep
their ids, status and `deleted_at`. A file path whose file is missing is
stored empty, with a warning.

After seeding, the public pages sort items by the admin's order (display
order, then name). The fallback used the order rows were stored in. Checked
2026-09-26: every page renders identically before and after, except two
Gallery photos and one Team card that move to their admin-set positions,
and one team photo whose file was missing, which no longer shows as a broken
image.

### schema.sql is now complete on its own

It previously omitted `content_blocks` entirely and `deleted_at` from six of
seven tables. Since every public query filters `deleted_at IS NULL` and every
page calls `block()`, a database built from `schema.sql` alone **returned a 500
on every public page while looking installed.**

It is now generated from the live schema and verified: a database created from
`schema.sql` alone is **structurally identical to live — 114 columns, 45
indexes, 3 FKs, zero differences** — and all 9 public read-layer queries run
against it.

`project_updates` and `content_blocks` were each defined in *both*
`schema.sql` and `migrate.php`. `migrate.php` now reads their DDL out of
`schema.sql` instead of carrying a second copy that could drift — and had:
its `content_blocks` copy still created an index that is dropped as redundant.

## Retention

```
php admin/database/prune-logs.php --dry-run
php admin/database/prune-logs.php            # activity_logs 365d, login_attempts 30d
```

Deletes in batches so a large first run cannot hold a long transaction or hit a
shared host's execution limit. Monthly cron is plenty.

## Data integrity

Audited across 20 checks — referential integrity, soft-delete consistency,
duplicates, required values, domain ranges, and file/DB agreement.
**0 issues.** 43 upload files on disk, 43 referenced, 0 orphaned, 0 missing.

Worth re-running after any bulk import: the script is
`scratchpad/integrity.php` in the Phase 4 working set; move it into `tools/` if
you want it permanently.

## Known normalisation note

`programs` stores `stat1_value/label`, `stat2_*`, `stat3_*` — a repeating group
that strict 1NF would split into a `program_stats` child table.

Left as-is deliberately: the public cards render exactly three stat tiles, so
the shape is fixed rather than open-ended, and splitting it would add a join
and a form rewrite for no behavioural change. Revisit if a card ever needs a
variable number of stats.

## Still outstanding

- **The codebase is still not in version control.** The database now has a
  tested backup; the code does not.
- `annual_reports` public query orders `display_order ASC, title DESC`. Mixed
  sort directions cannot be served by a single-direction index on MariaDB, so
  that one keeps its filesort. Harmless at this size; fix by adding a
  descending index if the table ever grows.
- Backups are local only. **Copy them offsite.**
