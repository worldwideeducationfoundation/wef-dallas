# Database

Last updated: 2026-10-14. The column list at the end is generated from
`website/public/admin/database/schema.sql`. Regenerate it after a schema change.

## Engine and settings

- MariaDB 10.4 locally (XAMPP); MySQL on Hostinger. Both work with the same
  schema.
- InnoDB tables, `utf8mb4` / `utf8mb4_unicode_ci`.
- Local database name: `wef_admin`. The live database name and user are in
  `website/config/config.php` (the LIVE block). They are not repeated here.
- 24 tables. 7 foreign keys, all to `admin_users` (the "who did this" links),
  plus `sponsors.group_id` → `sponsor_groups` (cascade) and
  `program_photos.program_id` → `programs` (cascade).

## Conventions

- Every content table has `id`, `display_order`, `status` (`active` or
  `draft`), `created_at`, `updated_at` and `deleted_at`.
- **Soft delete:** `deleted_at` is set when a row is moved to Trash. Public
  reads filter on `status = 'active' AND deleted_at IS NULL`.
- Image and file columns hold a path relative to `public/admin/uploads/`.
- `site_settings` and `constant_contact` are single-row tables.
- `content_blocks` holds only the admin's overrides of default copy. A missing
  row means the default from `BLOCK_REGISTRY` is shown. "Reset" deletes the row.
- `pages` holds the per-page banner image (`header_image`) and the SEO
  overrides are stored as `content_blocks` rows.
- `map_centers.district` and `center_model` are restricted to the lists in
  `modules/map-centers/_options.php`. Latitude and longitude are checked against
  a Chitral bounding box in `save.php`.

## Schema changes

1. Edit `schema.sql` for a **fresh install** only: its statements are
   `CREATE TABLE IF NOT EXISTS`, so edits do not reach an existing database.
2. Add the matching change to `admin/database/migrate.php` (idempotent: it
   checks before it alters). Run it locally. The live host has no shell, so a
   migration there needs a plan first: ask the owner before running anything
   against the live database.
3. Update the column list at the end of this file.

## Scripts in `website/public/admin/database/`

| Script | Use |
|---|---|
| `schema.sql` | Fresh install. Creates the database and all tables. |
| `migrate.php` | Brings an existing database up to date. Safe to run again. |
| `create-admin.php` | Creates a super admin from the command line. |
| `backup.php` | CLI. Writes a database dump and the uploads to `APP_BACKUP_DIR` (or `storage/backups/` locally). |
| `restore.php` | CLI. Restores a dump made by `backup.php`. |
| `prune-logs.php` | CLI. Trims `activity_logs` and `login_attempts`, which grow without limit. |
| `seed-*.php`, `import-*.php`, `backfill-*.php`, `migrate-*.php` | One-off or idempotent content loaders. Each says in its header whether it can run twice. Do not run them on the live site without checking first, because they can add rows. |

The seed scripts copied content out of `includes/static-data.php` and the
older hardcoded pages into the tables, so the tables now hold the live content.

## Data flows worth knowing

- **Form submissions:** `api/contact.php` writes `form_submissions` first. The
  newsletter path then calls Constant Contact and records the outcome on the
  same row (`newsletter_sync_error`).
- **Map:** `map_centers` (active rows) → `api/map-centers.php` → the home map.
- **Project pages:** `programs` (the page's stats and copy), `program_photos`
  (the carousel), and `programs.before_image_path` / `after_image_path` (the
  slider). The `video_gallery` row whose title matches a project shows beside
  its matching section.
- **Media Manager:** `TRASH_TYPES` lists the file columns, and
  `modules/media/_helpers.php` (`mediaReferenceColumns()`) lists every column
  that can point at an upload. Add any new file column to both.

## Tables and columns

Generated from `schema.sql`. Types are shown as the schema writes them.

### `admin_users`

`id` int(10) unsigned NOT NULL · `name` varchar(120) · `email` varchar(190) · `password_hash` varchar(255) · `role` enum('super_admin','administrator','editor') NOT NULL · `status` enum('active','disabled') NOT NULL · `last_login_at` datetime · `created_at` datetime NOT NULL

### `login_attempts`

`id` int(10) unsigned NOT NULL · `email` varchar(190) · `ip_address` varchar(45) · `success` tinyint(1) NOT NULL · `created_at` datetime NOT NULL

### `activity_logs`

`id` int(10) unsigned NOT NULL · `user_id` int(10) unsigned · `action` varchar(80) · `module` varchar(80) NOT NULL · `details` varchar(255) · `ip_address` varchar(45) · `user_agent` varchar(255) · `created_at` datetime NOT NULL

### `programs`

`id` int(10) unsigned NOT NULL · `slug` varchar(80) · `category` enum('early_childhood','programs','scholarships') · `name` varchar(160) · `summary` varchar(500) · `description` text · `badge_text` varchar(40) · `location_text` varchar(120) · `year_launched` smallint(5) unsigned · `stat1_value` varchar(40) · `stat1_label` varchar(60) · `stat2_value` varchar(40) · `stat2_label` varchar(60) · `stat3_value` varchar(40) · `stat3_label` varchar(60) · `image_path` varchar(255) · `before_image_path` varchar(255) · `after_image_path` varchar(255) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `success_stories`

`id` int(10) unsigned NOT NULL · `title` varchar(200) · `tag` varchar(40) · `story_date` varchar(40) · `image_path` varchar(255) · `link_url` varchar(255) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `video_gallery`

`id` int(10) unsigned NOT NULL · `title` varchar(200) · `youtube_url` varchar(255) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `completed_projects`

`id` int(10) unsigned NOT NULL · `slug` varchar(120) · `title` varchar(200) · `summary` varchar(500) · `description` mediumtext · `location` varchar(160) · `completion_year` varchar(20) · `category` varchar(80) · `image_path` varchar(255) · `secondary_image_path` varchar(255) · `tertiary_image_path` varchar(255) · `map_image_path` varchar(255) · `video_url` varchar(255) · `stat1_value` varchar(40) · `stat1_label` varchar(60) · `stat2_value` varchar(40) · `stat2_label` varchar(60) · `stat3_value` varchar(40) · `stat3_label` varchar(60) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `mission_projects`

`id` int(10) unsigned NOT NULL · `what_header` varchar(200) · `what_sub` varchar(400) · `launch_year` varchar(20) · `where_text` text · `who_text` text · `impact_text` text · `notes_text` text · `notes_highlight` text · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `impact_milestones`

`id` int(10) unsigned NOT NULL · `milestone_date` varchar(40) · `centers_target` int(10) unsigned NOT NULL · `students_target` int(10) unsigned NOT NULL · `teachers_target` int(10) unsigned NOT NULL · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `team_members`

`id` int(10) unsigned NOT NULL · `name` varchar(160) · `role` varchar(160) · `section` varchar(80) NOT NULL · `bio` text · `image_path` varchar(255) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `sponsor_groups`

`id` int(10) unsigned NOT NULL · `title` varchar(255) · `description` text · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `sponsors`

`id` int(10) unsigned NOT NULL · `group_id` int(10) unsigned · `name` varchar(255) · `badge_text` varchar(60) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `gallery_items`

`id` int(10) unsigned NOT NULL · `caption` varchar(160) · `program_id` int(10) unsigned · `image_path` varchar(255) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `annual_reports`

`id` int(10) unsigned NOT NULL · `title` varchar(160) · `description` text · `cover_image_path` varchar(255) · `pdf_path` varchar(255) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `project_updates`

`id` int(10) unsigned NOT NULL · `title` varchar(160) · `slug` varchar(160) · `content` text · `link_url` varchar(255) · `image_path` varchar(255) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `site_settings`

`id` int(10) unsigned NOT NULL · `contact_email` varchar(190) · `contact_phone` varchar(60) · `contact_address` varchar(255) · `facebook_url` varchar(255) · `twitter_url` varchar(255) · `instagram_url` varchar(255) · `linkedin_url` varchar(255) · `youtube_url` varchar(255) · `donate_url` varchar(255) · `updated_at` datetime NOT NULL

### `form_submissions`

`id` int(10) unsigned NOT NULL · `form_type` varchar(40) NOT NULL · `name` varchar(160) · `email` varchar(190) · `subject` varchar(60) · `message` text · `status` enum('new','read','replied','archived') NOT NULL · `admin_note` varchar(1000) · `ip_address` varchar(45) · `user_agent` varchar(255) · `handled_by` int(10) unsigned · `handled_at` datetime · `newsletter_sync_error` varchar(255) · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `content_blocks`

`id` int(10) unsigned NOT NULL · `page` varchar(80) · `block_key` varchar(80) · `value` text · `updated_at` datetime NOT NULL · `updated_by` int(10) unsigned

### `constant_contact`

`id` tinyint(3) unsigned NOT NULL · `access_token` text · `refresh_token` text · `expires_at` datetime · `scope` varchar(255) · `list_id` varchar(64) · `list_name` varchar(190) · `connected_at` datetime · `connected_by` int(10) unsigned · `last_error` varchar(500) · `last_error_at` datetime · `updated_at` datetime NOT NULL

### `hero_slides`

`id` int(10) unsigned NOT NULL · `title_lead` varchar(120) · `title_highlight` varchar(120) · `description` varchar(400) · `image_path` varchar(255) · `image_alt` varchar(200) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `partners`

`id` int(10) unsigned NOT NULL · `name` varchar(200) · `logo_path` varchar(255) · `link_url` varchar(255) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

### `pages`

`page_key` varchar(60) · `header_image` varchar(255) · `updated_at` datetime NOT NULL · `updated_by` int(10) unsigned

### `program_photos`

`id` int(10) unsigned NOT NULL · `program_id` int(10) unsigned · `image_path` varchar(255) · `caption` varchar(160) · `display_order` int(11) NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL

### `map_centers`

`id` int(10) unsigned NOT NULL · `lead_name` varchar(160) · `sponsor` varchar(255) · `village` varchar(255) · `district` enum('Upper Chitral','Lower Chitral') · `center_model` enum('Entrepreneurial Model','Government School','Private School','Elementary School') · `latitude` decimal(9,6) · `longitude` decimal(9,6) · `display_order` int(11) NOT NULL · `status` enum('draft','active') NOT NULL · `created_at` datetime NOT NULL · `updated_at` datetime NOT NULL · `deleted_at` datetime

