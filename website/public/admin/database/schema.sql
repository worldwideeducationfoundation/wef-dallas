-- WEF — complete database schema
--
-- Generated from the live schema. This file alone is enough to create a
-- working database: run it, then create the first account.
--
--   1. mysql < admin/database/schema.sql
--   2. php admin/database/create-admin.php "Full Name" you@example.org
--   3. php admin/database/migrate.php            -- no-op on a fresh install
--
-- Why step 3 is still listed: migrate.php is the mechanism for reaching a
-- database that ALREADY EXISTS. Editing this file only ever affects a fresh
-- install, because every statement here is CREATE TABLE IF NOT EXISTS. Any
-- change that has to reach an existing install -- a new column, a new index --
-- belongs in migrate.php, which is idempotent and safe to re-run.
--
-- This file used to be incomplete in a way that was easy to miss: it omitted
-- the content_blocks table entirely and the deleted_at column on six of the
-- seven tables that need it. Since every public query filters
-- "deleted_at IS NULL" and every page calls block(), a database built from
-- schema.sql alone returned a 500 on every public page -- while looking
-- installed. Regenerating from the live schema is what keeps the two honest.
--
-- Charset/collation are explicit so emoji and accented names in team bios and
-- sponsor names store correctly without a later migration.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS wef_admin
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE wef_admin;

-- --------------------------------------------------------------------
-- admin_users
-- Roles map to capabilities in admin/includes/auth.php (ROLE_CAPABILITIES).
-- super_admin holds the '*' wildcard.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','administrator','editor') NOT NULL DEFAULT 'super_admin',
  `status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_users_email` (`email`),
  KEY `idx_admin_users_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- login_attempts
-- Single source of truth for BOTH rate limits: per-email and per-IP.
-- Deliberately not a failed_attempts/locked_until pair of columns on
-- admin_users, which would just be a second, driftable copy. Rows are
-- pruned by admin/database/prune-logs.php.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_email_time` (`email`,`created_at`),
  KEY `idx_login_attempts_ip_time` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- activity_logs
-- The one table that grows without bound. Pruned by
-- admin/database/prune-logs.php (365-day default retention).
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `module` varchar(80) NOT NULL DEFAULT 'auth',
  `details` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_user` (`user_id`),
  KEY `idx_activity_module_time` (`module`,`created_at`),
  KEY `idx_activity_action_time` (`action`,`created_at`),
  KEY `idx_activity_created_id` (`created_at`,`id`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- programs
-- summary (card blurb) and description (full page body) are separate
-- columns because that is how the content already exists on the site.
-- image_path is relative to admin/uploads/.
--
-- Known normalisation note: stat1/stat2/stat3 value+label pairs are a
-- repeating group, which strict 1NF would split into a program_stats
-- child table. Left as-is deliberately: the public cards render exactly
-- three stat tiles, so the shape is fixed rather than open-ended, and
-- splitting it would add a join and a form rewrite for no behaviour change.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `programs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `category` enum('early_childhood','programs','scholarships') NOT NULL,
  `name` varchar(160) NOT NULL,
  `summary` varchar(500) NOT NULL,
  `description` text NOT NULL,
  `badge_text` varchar(40) DEFAULT NULL,
  `location_text` varchar(120) DEFAULT NULL,
  `year_launched` smallint(5) unsigned DEFAULT NULL,
  `stat1_value` varchar(40) DEFAULT NULL,
  `stat1_label` varchar(60) DEFAULT NULL,
  `stat2_value` varchar(40) DEFAULT NULL,
  `stat2_label` varchar(60) DEFAULT NULL,
  `stat3_value` varchar(40) DEFAULT NULL,
  `stat3_label` varchar(60) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `before_image_path` varchar(255) DEFAULT NULL COMMENT 'Before/after slider on the project page: shown only when both are set',
  `after_image_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programs_slug` (`slug`),
  KEY `idx_programs_listing` (`category`,`display_order`,`name`),
  KEY `idx_programs_public` (`status`,`deleted_at`,`category`,`display_order`,`name`),
  KEY `idx_programs_admin` (`deleted_at`,`category`,`display_order`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- success_stories
-- Was 6 hardcoded cards on success-stories.php, several already
-- placeholder/duplicate content (gray "Image Space" boxes, href="#" read-more
-- links, one title repeated three times with drifting punctuation) rather than
-- finished copy — that placeholder text is preserved as-is on migration, not
-- rewritten, since inventing "real" story content is not this migration's job.
--
-- link_url is nullable and plain: no detail-page template exists for these
-- stories yet, so a story without one simply renders without a "Read More"
-- link, the same pattern project_updates already uses for its own link_url.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `success_stories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `tag` varchar(40) DEFAULT NULL,
  `story_date` varchar(40) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_success_stories_listing` (`display_order`,`title`),
  KEY `idx_success_stories_public` (`status`,`deleted_at`,`display_order`,`title`),
  KEY `idx_success_stories_admin` (`deleted_at`,`display_order`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- video_gallery
-- Was 2 hardcoded YouTube embeds on video-gallery.php, duplicated a third
-- time on index.php's own "Featured Videos" section (see that page's
-- $featuredVideos — both now read the same table, so there is one place to
-- add a video instead of three).
--
-- youtube_url is validated on save (admin/modules/video-gallery/save.php)
-- against a youtube.com/youtube-nocookie.com host allow-list before it is
-- ever built into an <iframe src> — the same reasoning as
-- completed_projects.video_url (see that table's comment above).
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `video_gallery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `youtube_url` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_video_gallery_listing` (`display_order`,`title`),
  KEY `idx_video_gallery_public` (`status`,`deleted_at`,`display_order`,`title`),
  KEY `idx_video_gallery_admin` (`deleted_at`,`display_order`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- completed_projects
-- Was includes/completed-projects-data.php, a 16-entry PHP array whose own
-- header comment called it "a mock database layer... once a backend
-- developer creates the real table, they can simply replace the array with
-- a PDO fetch call". This is that table.
--
-- map_embed/video_embed (raw HTML columns) existed briefly for a read-only
-- version of this data and are gone from a fresh install. They stored an
-- <img> or YouTube <iframe> as a literal string, output unescaped by the
-- template — fine with no admin form writing to them, but the day one did,
-- it would have been a stored-XSS hole. Replaced before any admin form was
-- built: map_image_path is an uploaded image like the others, video_url is
-- a plain YouTube URL, and the template builds the tag from both safely.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `completed_projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) NOT NULL,
  `title` varchar(200) NOT NULL,
  `summary` varchar(500) NOT NULL,
  `description` mediumtext NOT NULL,
  `location` varchar(160) DEFAULT NULL,
  `completion_year` varchar(20) DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `secondary_image_path` varchar(255) DEFAULT NULL,
  `tertiary_image_path` varchar(255) DEFAULT NULL,
  `map_image_path` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `stat1_value` varchar(40) DEFAULT NULL,
  `stat1_label` varchar(60) DEFAULT NULL,
  `stat2_value` varchar(40) DEFAULT NULL,
  `stat2_label` varchar(60) DEFAULT NULL,
  `stat3_value` varchar(40) DEFAULT NULL,
  `stat3_label` varchar(60) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_completed_projects_slug` (`slug`),
  KEY `idx_completed_projects_public` (`status`,`deleted_at`,`display_order`,`title`),
  KEY `idx_completed_projects_admin` (`deleted_at`,`display_order`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- mission_projects
-- The 9-row "WEF At A Glance" table on our-mission.php (WHAT / WHERE / WHO
-- / IMPACT / NOTES) — structured tabular data, not prose, so it never fit
-- content_blocks the way the rest of that page's copy does.
--
-- where_text/who_text/impact_text/notes_text each hold one bullet per line
-- (rendered as a <ul><li> per line, same "one line, one item" convention
-- admin/modules/*/form.php already uses nowhere else, so a new, very small
-- renderer — missionBulletList() in includes/content.php — does just that,
-- rather than reusing content-blocks.php's blockBody(), which also parses
-- paragraphs and ## headings this table never uses).
--
-- notes_highlight is the separate bordered callout box under the NOTES
-- bullets (an investment/funding figure, on 6 of the 9 source rows) — kept
-- as its own nullable column rather than folded into notes_text, since it
-- renders with different markup, not as another bullet.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mission_projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `what_header` varchar(200) NOT NULL,
  `what_sub` varchar(400) DEFAULT NULL,
  `launch_year` varchar(20) DEFAULT NULL,
  `where_text` text DEFAULT NULL,
  `who_text` text DEFAULT NULL,
  `impact_text` text DEFAULT NULL,
  `notes_text` text DEFAULT NULL,
  `notes_highlight` text DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mission_projects_listing` (`display_order`,`what_header`),
  KEY `idx_mission_projects_public` (`status`,`deleted_at`,`display_order`,`what_header`),
  KEY `idx_mission_projects_admin` (`deleted_at`,`display_order`,`what_header`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- impact_milestones
-- The 5-point roadmap chart on impact-goals.php — each row is one point
-- on the ascending line (a target date plus 3 cumulative targets: learning
-- centers, students impacted, teachers/entrepreneurs trained).
--
-- The chart's SVG geometry (each point's x/y position on the curve, the
-- background bars, the timeline arrow) is a fixed 5-slot layout hand-tuned
-- to fit the 1200x900 viewBox — not data, so it stays in impact-goals.php
-- as a constant array, positionally zipped with up to the first 5 active
-- rows here by display_order. That is a real limit (a 6th active row
-- never renders, and the timeline/arrow are drawn assuming exactly 5 are
-- present) rather than an arbitrary-length chart, which matches how this
-- roadmap has always been presented — 5 milestones from launch to 2031 —
-- and avoids reflowing hand-designed chart geometry from a variable-length
-- data set.
--
-- The 3-card summary beneath the chart (Learning Centers / Students
-- Impacted / Teachers Trained) shows the LAST active milestone's own 3
-- targets rather than its own separately stored numbers — the original
-- hardcoded page already did exactly this (its summary numbers matched
-- the final chart point exactly), so storing them a second time would
-- only add a way for the two to quietly disagree.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `impact_milestones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `milestone_date` varchar(40) NOT NULL,
  `centers_target` int(10) unsigned NOT NULL DEFAULT 0,
  `students_target` int(10) unsigned NOT NULL DEFAULT 0,
  `teachers_target` int(10) unsigned NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_impact_milestones_listing` (`display_order`,`milestone_date`),
  KEY `idx_impact_milestones_public` (`status`,`deleted_at`,`display_order`),
  KEY `idx_impact_milestones_admin` (`deleted_at`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- team_members
-- No slug: team members open in a modal keyed by index on the public
-- site, not routed to their own page.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `team_members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `role` varchar(160) NOT NULL,
  `section` varchar(80) NOT NULL DEFAULT 'Advisory Board Members',
  `bio` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_team_listing` (`display_order`,`name`),
  KEY `idx_team_public` (`status`,`deleted_at`,`display_order`,`name`),
  KEY `idx_team_admin` (`deleted_at`,`display_order`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- sponsor_groups
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sponsor_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sponsor_groups_listing` (`display_order`,`title`),
  KEY `idx_sponsor_groups_public` (`status`,`deleted_at`,`display_order`,`title`),
  KEY `idx_sponsor_groups_admin` (`deleted_at`,`display_order`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- sponsors
-- ON DELETE CASCADE fires only on a real DELETE. With soft delete the
-- cascade is done explicitly in trashItems() -- see admin/includes/trash.php.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sponsors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `badge_text` varchar(60) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sponsors_listing` (`group_id`,`display_order`,`name`),
  KEY `idx_sponsors_public` (`status`,`deleted_at`,`display_order`,`name`),
  KEY `idx_sponsors_admin` (`deleted_at`,`group_id`,`display_order`,`name`),
  CONSTRAINT `fk_sponsors_group` FOREIGN KEY (`group_id`) REFERENCES `sponsor_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- gallery_items
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gallery_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `caption` varchar(160) NOT NULL,
  `program_id` int(10) unsigned DEFAULT NULL COMMENT 'Project the photo belongs to (programs.id); NULL = general',
  `image_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gallery_listing` (`display_order`,`caption`),
  KEY `idx_gallery_public` (`status`,`deleted_at`,`display_order`,`caption`),
  KEY `idx_gallery_admin` (`deleted_at`,`display_order`,`caption`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- annual_reports
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `annual_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image_path` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reports_listing` (`display_order`,`title`),
  KEY `idx_reports_public` (`status`,`deleted_at`,`display_order`,`title`),
  KEY `idx_reports_admin` (`deleted_at`,`display_order`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- project_updates
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `project_updates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(160) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `content` text NOT NULL,
  -- Category chip shown on the card ("Impact", "Announcement", ...) and the
  -- URL its READ MORE link points at. Both nullable: a card without a chip
  -- renders without one, and a card without a link renders as plain text.
  `tag` varchar(40) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_updates_slug` (`slug`),
  KEY `idx_project_updates_listing` (`display_order`,`title`),
  KEY `idx_updates_public` (`status`,`deleted_at`,`display_order`,`title`),
  KEY `idx_updates_admin` (`deleted_at`,`display_order`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- site_settings
-- One row, pinned by the CHECK constraint. settings/index.php inserts it
-- with NULLs on first load rather than ever allowing a second row.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(10) unsigned NOT NULL DEFAULT 1,
  `contact_email` varchar(190) DEFAULT NULL,
  `contact_phone` varchar(60) DEFAULT NULL,
  `contact_address` varchar(255) DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `twitter_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `youtube_url` varchar(255) DEFAULT NULL,
  `donate_url` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_site_settings_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- form_submissions
-- Everything the public contact and volunteer forms send. One table
-- rather than two: the two forms collect the same four fields, differing
-- only in which subjects they offer, so splitting them would mean two
-- tables, two admin lists and two exports to keep in step for no gain.
-- form_type is what tells them apart.
--
-- form_type and subject are validated against SUBMISSION_FORMS in
-- includes/form-submissions.php, not by the column type -- subject is a
-- plain varchar deliberately, so adding a topic to a form's dropdown is a
-- one-line edit in the registry rather than an ALTER TABLE on a table that
-- already holds rows using the old set.
--
-- status is the triage workflow (new -> read -> replied -> archived), not
-- the draft/active publishing state every other table uses; nothing here is
-- ever rendered on the public site.
--
-- ip_address is recorded for the per-IP flood check in the submit endpoint,
-- which counts recent rows from the same address -- idx_submissions_rate
-- is that query's index.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `form_type` varchar(40) NOT NULL DEFAULT 'contact',
  `name` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `subject` varchar(60) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied','archived') NOT NULL DEFAULT 'new',
  `admin_note` varchar(1000) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `handled_by` int(10) unsigned DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL,
  -- Outcome of pushing a newsletter signup to the mailing-list provider
  -- (Constant Contact -- see includes/constant-contact.php). Named for the
  -- purpose rather than the vendor so changing provider is a config change,
  -- not an ALTER TABLE on a table that already holds rows.
  --
  -- Both NULL on a contact or volunteer submission, which is never pushed
  -- anywhere. On a newsletter row exactly one of them is set: a timestamp
  -- when the address reached the list, or the reason it did not. The error
  -- is kept rather than only logged because a failure nobody can see is how
  -- a month of signups goes missing.
  `newsletter_synced_at` datetime DEFAULT NULL,
  `newsletter_sync_error` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_submissions_admin` (`deleted_at`,`created_at`,`id`),
  KEY `idx_submissions_type` (`deleted_at`,`form_type`,`created_at`),
  KEY `idx_submissions_status` (`deleted_at`,`status`,`created_at`),
  KEY `idx_submissions_rate` (`ip_address`,`created_at`),
  KEY `fk_form_submissions_user` (`handled_by`),
  CONSTRAINT `fk_form_submissions_user` FOREIGN KEY (`handled_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- content_blocks
-- Stores OVERRIDES ONLY. Default copy lives in BLOCK_REGISTRY
-- (includes/content-blocks.php), so a page renders correctly against an
-- empty table and "reset to default" is a row delete.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `content_blocks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page` varchar(80) NOT NULL,
  `block_key` varchar(80) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_content_blocks` (`page`,`block_key`),
  KEY `fk_content_blocks_user` (`updated_by`),
  CONSTRAINT `fk_content_blocks_user` FOREIGN KEY (`updated_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- constant_contact
-- The OAuth connection to Constant Contact, one row pinned like
-- site_settings. See includes/constant-contact.php for why a mailing-list
-- integration needs stored state at all: Constant Contact has no API-key
-- auth and no machine-to-machine flow, so an administrator authorises once
-- in a browser and the refresh token issued then is what keeps the
-- integration alive afterwards.
--
-- WHAT IS AND IS NOT HERE. The tokens live in this table because they
-- rotate at runtime and config/config.php is not writable. CC_CLIENT_ID and
-- CC_CLIENT_SECRET deliberately do NOT: admin/database/backup.php produces
-- a downloadable dump, and a refresh token in that dump is inert without
-- the client secret needed to Basic-auth the token request. Keep it that
-- way -- moving the secret in here would make a backup file sufficient on
-- its own to take over the mailing list.
--
-- access_token is TEXT because Constant Contact issues JWTs well past the
-- 255 bytes a varchar would give.
--
-- list_id is the mailing list signups are added to, chosen in
-- admin/modules/newsletter/. list_name is a cached label for the admin
-- screen only -- list_id is the value anything acts on.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `constant_contact` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `scope` varchar(255) DEFAULT NULL,
  `list_id` varchar(64) DEFAULT NULL,
  `list_name` varchar(190) DEFAULT NULL,
  `connected_at` datetime DEFAULT NULL,
  `connected_by` int(10) unsigned DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `last_error_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_constant_contact_user` (`connected_by`),
  CONSTRAINT `fk_constant_contact_user` FOREIGN KEY (`connected_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_constant_contact_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- hero_slides
-- The home page slideshow: 9 photo + headline + description slides that
-- were hardcoded in index.php. The headline is two parts because the
-- design types the first part in white and the second in the brand-green
-- italic ("Early Childhood " + "Learning Centers (ECLC)").
--
-- image_path is relative to admin/uploads/ like every other upload; the
-- first active slide's photo is also preloaded from <head> (it is the
-- page's largest paint), so it follows whatever slide is ordered first.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hero_slides` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title_lead` varchar(120) NOT NULL,
  `title_highlight` varchar(120) NOT NULL,
  `description` varchar(400) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_alt` varchar(200) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hero_slides_listing` (`display_order`,`title_highlight`),
  KEY `idx_hero_slides_public` (`status`,`deleted_at`,`display_order`),
  KEY `idx_hero_slides_admin` (`deleted_at`,`display_order`,`title_highlight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- partners
-- The "Our Partners" logo strip on the home page, hardcoded in index.php
-- as 6 logos (repeated once by the template for the endless scroll).
-- link_url is optional; a logo without one renders as a plain image.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `partners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_partners_listing` (`display_order`,`name`),
  KEY `idx_partners_public` (`status`,`deleted_at`,`display_order`),
  KEY `idx_partners_admin` (`deleted_at`,`display_order`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- pages
-- Per-page settings that are not text blocks. Page text stays in
-- content_blocks (keyed by the same page key); this holds the optional
-- header_image shown behind the green banner on internal pages (Contact,
-- Gallery, Volunteer, ...). page_key matches the keys in
-- includes/content-blocks.php. header_image is relative to admin/uploads/.
-- is_enabled is the switch in admin -> Pages On/Off, keyed by the page's
-- address ("our-team", "donate"; see SWITCHABLE_PAGES in
-- includes/page-visibility.php). 0 = off: out of the header, links to it
-- disabled, its address answers 404. A page with no row is on.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
  `page_key` varchar(60) NOT NULL,
  `header_image` varchar(255) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`page_key`),
  KEY `fk_pages_user` (`updated_by`),
  CONSTRAINT `fk_pages_user` FOREIGN KEY (`updated_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------
-- program_photos
-- The photos of a project page (admin -> Our Active Projects -> edit ->
-- Project photos): they illustrate the page's alternating sections in
-- display_order, and all of them run in the photo carousel at the foot of
-- the page. Deleted outright with their file (no Trash): a photo is part of
-- its project's form, not a content item of its own.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `program_photos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` int(10) unsigned NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(160) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_program_photos_program` (`program_id`,`display_order`,`id`),
  CONSTRAINT `fk_program_photos_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- map_centers
-- The learning centers on the home page's interactive map (admin -> Map
-- Centers). Served to the map as GeoJSON by public/api/map-centers.php,
-- which falls back to public/assets/data/locations_map1.geojson (the file
-- the table was first filled from) if the table is empty or unreachable.
-- lead_name is the woman entrepreneur or teacher running the center.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `map_centers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lead_name` varchar(160) DEFAULT NULL,
  `sponsor` varchar(255) NOT NULL,
  `village` varchar(255) NOT NULL,
  `district` enum('Upper Chitral','Lower Chitral') NOT NULL,
  `center_model` enum('Entrepreneurial Model','Government School','Private School','Semi Govt School') NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_map_centers_admin` (`deleted_at`,`display_order`),
  KEY `idx_map_centers_public` (`status`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- donate_options
-- The Program dropdown on the Donate page (admin -> Donate Options), in
-- display_order. An option may be linked to a project (program_id; no
-- foreign key, programs are soft-deleted): it then takes that project's
-- name, photo, description and numbers wherever its own label, image_path
-- or summary is empty, and is hidden while the project is unpublished.
-- slug is the option's name in the address (donate?program=<slug>); the
-- project pages' Donate links use the project's slug, so linked options
-- keep it. "general" is the default ("Where it's needed most").
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donate_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(200) DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_donate_options_slug` (`slug`),
  KEY `idx_donate_options_admin` (`deleted_at`,`display_order`),
  KEY `idx_donate_options_public` (`status`,`deleted_at`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
