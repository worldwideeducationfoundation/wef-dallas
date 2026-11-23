<?php
/**
 * Idempotent schema migrations.
 *
 * schema.sql is all CREATE TABLE IF NOT EXISTS, which means edits to it
 * only ever reach a fresh install — an existing database never picks them
 * up. This runner applies changes to databases that already exist, and is
 * safe to run repeatedly: every step checks whether it's already been
 * applied before doing anything.
 *
 * Usage, from the project root:
 *   php admin/database/migrate.php          # apply
 *   php admin/database/migrate.php --dry-run # show what would change
 *
 * CREATE INDEX IF NOT EXISTS is deliberately not used: MariaDB supports
 * it, MySQL 8 does not, and this has to run on both (XAMPP locally,
 * whatever Hostinger provides in production).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/database.php';

$dryRun = in_array('--dry-run', $argv, true);
$db = getDb();

function indexExists(PDO $db, string $table, string $index): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i'
    );
    $stmt->execute(['t' => $table, 'i' => $index]);

    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c'
    );
    $stmt->execute(['t' => $table, 'c' => $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :t'
    );
    $stmt->execute(['t' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Column additions run FIRST — some of the indexes below are on columns
 * added here, so creating indexes ahead of them would fail on a database
 * that predates those columns.
 */
$columns = [
    // The Donate buttons (header, hero, Donate section) linked to a Zeffy
    // form typed into three templates. Nullable: empty means the built-in
    // default in donateUrl() (includes/content.php).
    [
        'table' => 'site_settings',
        'column' => 'donate_url',
        'definition' => 'VARCHAR(255) NULL AFTER youtube_url',
    ],
    // The Gallery page labels, filters and groups photos by project.
    // Nullable: a photo with no project (a landscape, an event) shows under
    // "General". No foreign key - programs are soft-deleted, and the page
    // query only joins live ones.
    [
        'table' => 'gallery_items',
        'column' => 'program_id',
        'definition' => 'INT UNSIGNED NULL AFTER caption',
    ],
    // Before/after photo pair for the comparison slider on a project's
    // page. The slider is drawn only when both are set, so a project with
    // nothing to compare simply has none.
    [
        'table' => 'programs',
        'column' => 'before_image_path',
        'definition' => 'VARCHAR(255) NULL AFTER image_path',
    ],
    [
        'table' => 'programs',
        'column' => 'after_image_path',
        'definition' => 'VARCHAR(255) NULL AFTER before_image_path',
    ],
    [
        'table' => 'team_members',
        'column' => 'section',
        'definition' => "VARCHAR(80) NOT NULL DEFAULT 'Advisory Board Members' AFTER role",
    ],
    [
        'table' => 'programs',
        'column' => 'year_launched',
        'definition' => 'SMALLINT UNSIGNED NULL AFTER location_text',
    ],
    /**
     * The home page's Project Updates cards carry two things the table had no
     * room for: a coloured category chip ("Impact", "Announcement",
     * "Education", "Community") and the URL its READ MORE link points at.
     *
     * Added when those nine hardcoded cards were imported so the CMS could
     * manage them. Without these columns the import would have silently
     * dropped the chip and sent every card to the same place — the module
     * would have "worked" while quietly degrading the page.
     *
     * Both nullable: a card with no chip simply renders without one.
     */
    [
        'table' => 'project_updates',
        'column' => 'tag',
        'definition' => 'VARCHAR(40) NULL AFTER content',
    ],
    [
        'table' => 'project_updates',
        'column' => 'link_url',
        'definition' => 'VARCHAR(255) NULL AFTER tag',
    ],
    /**
     * Outcome of pushing a newsletter signup to Constant Contact.
     *
     * Both nullable and both empty on every existing row, which is correct:
     * signups stored before the integration existed were never pushed
     * anywhere, and backfilling them with a timestamp would claim they had
     * been. The Submissions module reads "neither set" as "not synced" and
     * offers the retry button, so historic rows can be sent deliberately
     * rather than silently marked done.
     */
    [
        'table' => 'form_submissions',
        'column' => 'newsletter_synced_at',
        'definition' => 'DATETIME NULL DEFAULT NULL AFTER handled_at',
    ],
    [
        'table' => 'form_submissions',
        'column' => 'newsletter_sync_error',
        'definition' => 'VARCHAR(255) NULL DEFAULT NULL AFTER newsletter_synced_at',
    ],
    /**
     * Replace completed_projects' raw-HTML map_embed/video_embed columns
     * (a stored-XSS risk the moment an admin form writes to them — see
     * schema.sql's comment on this table) with structured fields the
     * template builds safe markup from. Added here rather than only in
     * schema.sql because this table was created mid-session, before this
     * fix, so an install that already ran migrate.php once needs these
     * added explicitly. See migrate-completed-projects-embeds.php for the
     * one-off step that copies data out of the old columns and drops them.
     */
    [
        'table' => 'completed_projects',
        'column' => 'map_image_path',
        'definition' => 'VARCHAR(255) NULL DEFAULT NULL AFTER tertiary_image_path',
    ],
    [
        'table' => 'completed_projects',
        'column' => 'video_url',
        'definition' => 'VARCHAR(255) NULL DEFAULT NULL AFTER map_image_path',
    ],
    // admin -> Pages On/Off. Default 1: every existing page stays on.
    [
        'table' => 'pages',
        'column' => 'is_enabled',
        'definition' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER header_image',
    ],
];

// Soft delete. Deleting content used to be an immediate DELETE with no
// way back — one mis-click and a bio with years of history was gone.
// Rows now get a deleted_at stamp instead and move to Trash, where they
// can be restored or purged deliberately.
foreach (['programs', 'team_members', 'gallery_items', 'annual_reports', 'sponsors', 'sponsor_groups'] as $softDeleteTable) {
    $columns[] = [
        'table' => $softDeleteTable,
        'column' => 'deleted_at',
        'definition' => 'DATETIME NULL DEFAULT NULL',
    ];
}

$applied = 0;
$skipped = 0;

/**
 * New tables. Same idempotent style as everything else here: CREATE TABLE
 * IF NOT EXISTS, so re-running is free.
 *
 * content_blocks holds editable copy for the parts of the site that are
 * not one of the six content types (programs, team, gallery, reports,
 * sponsors, settings) — page headings, intro paragraphs, mission
 * statements and so on, which until now were hardcoded in 18 PHP files
 * with no way to change them without editing code.
 *
 * Only overrides are stored. The default text lives in the block registry
 * (includes/content-blocks.php), so a page renders correctly with an
 * empty table and "reset to default" is always possible.
 */
$tables = [
    // content_blocks, like project_updates, is defined in schema.sql and read
    // from there. Keeping a second copy here meant the two could drift — and
    // they had: this copy still created idx_content_blocks_page, an index that
    // is a strict prefix of uq_content_blocks and is dropped as redundant
    // further down. A fresh install would have created it and then removed it.
    'content_blocks' => null,
    // project_updates used to be defined here AS WELL AS in schema.sql — the
    // same table, written out twice, with nothing keeping the two in step. It
    // now lives only in schema.sql for a fresh install; this entry stays so an
    // existing database that predates the table still gets it, but the DDL is
    // read from schema.sql rather than duplicated.
    'project_updates' => null,
    // Contact + volunteer form submissions. Same arrangement: defined once in
    // schema.sql, listed here so a database created before the forms had a
    // backend picks the table up on the next migrate run.
    'form_submissions' => null,
    // The Constant Contact OAuth connection (one pinned row). Same
    // arrangement again — schema.sql owns the definition, this entry is what
    // gets it onto a database that predates the newsletter integration.
    'constant_contact' => null,
    // Replaces includes/completed-projects-data.php's 16-entry PHP array.
    // Same arrangement: defined once in schema.sql, listed here so an
    // existing database picks it up on the next migrate run.
    'completed_projects' => null,
    // Replace the hardcoded cards on success-stories.php / video-gallery.php.
    // Same arrangement: defined once in schema.sql, listed here so an
    // existing database picks them up on the next migrate run.
    'success_stories' => null,
    'video_gallery' => null,
    // Replaces the 9-row "WEF At A Glance" table hardcoded into
    // our-mission.php. Same arrangement: defined once in schema.sql, listed
    // here so an existing database picks it up on the next migrate run.
    'mission_projects' => null,
    // Replaces the 5-point roadmap chart hardcoded into impact-goals.php.
    // Same arrangement: defined once in schema.sql, listed here so an
    // existing database picks it up on the next migrate run.
    'impact_milestones' => null,
    // Replace the home page's hardcoded hero slideshow and partner logos.
    // Same arrangement: defined once in schema.sql.
    'hero_slides' => null,
    'partners' => null,
    // Per-page header images for the internal-page banners.
    'pages' => null,
    // The photo set of each project page (alternating sections + carousel).
    'program_photos' => null,
    // The learning centers on the home page map (admin -> Map Centers).
    'map_centers' => null,
    // The Program dropdown on the Donate page (admin -> Donate Options).
    'donate_options' => null,
];

/**
 * Pulls one table's CREATE statement out of schema.sql, so a table that exists
 * in both places is defined in only one of them. Returns null if not found.
 */
function ddlFromSchemaFile(string $table): ?string
{
    static $sql = null;
    if ($sql === null) {
        $path = __DIR__ . '/schema.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
    }

    if (preg_match(
        '/CREATE TABLE IF NOT EXISTS `?' . preg_quote($table, '/') . '`?\s*\(.*?\n\)[^;]*;/s',
        $sql,
        $m
    )) {
        return rtrim($m[0], ';');
    }

    return null;
}

foreach ($tables as $tableName => $ddl) {
    if (tableExists($db, $tableName)) {
        $skipped++;
        continue;
    }

    // A null entry means "schema.sql owns this definition".
    $ddl ??= ddlFromSchemaFile($tableName);

    if ($ddl === null) {
        echo "  SKIP  {$tableName} — no DDL here and none found in schema.sql\n";
        continue;
    }

    if ($dryRun) {
        echo "  WOULD RUN  CREATE TABLE {$tableName}\n";
        $applied++;
        continue;
    }

    $db->exec($ddl);
    echo "  CREATED TABLE  {$tableName}\n";
    $applied++;
}

foreach ($columns as $spec) {
    if (!tableExists($db, $spec['table'])) {
        echo "  SKIP  {$spec['table']}.{$spec['column']} — table does not exist\n";
        continue;
    }

    if (columnExists($db, $spec['table'], $spec['column'])) {
        $skipped++;
        continue;
    }

    $sql = "ALTER TABLE {$spec['table']} ADD COLUMN {$spec['column']} {$spec['definition']}";

    if ($dryRun) {
        echo "  WOULD RUN  {$sql}\n";
        $applied++;
        continue;
    }

    $db->exec($sql);
    echo "  ADDED COLUMN  {$spec['table']}.{$spec['column']}\n";
    $applied++;
}

/**
 * Every index below backs an ORDER BY, WHERE, or LIKE that a module page
 * actually runs — none are speculative. Before this, every list page was
 * a full scan plus a filesort.
 */
$indexes = [
    // ORDER BY category, display_order, name  (modules/programs/index.php)
    ['programs', 'idx_programs_listing', '(category, display_order, name)'],

    // ORDER BY display_order, name  (modules/team/index.php)
    ['team_members', 'idx_team_listing', '(display_order, name)'],

    // ORDER BY display_order, caption  (modules/gallery/index.php)
    ['gallery_items', 'idx_gallery_listing', '(display_order, caption)'],

    // ORDER BY display_order  (modules/reports/index.php)
    ['annual_reports', 'idx_reports_listing', '(display_order, title)'],

    // ORDER BY g.display_order, g.title  (modules/sponsors/index.php)
    ['sponsor_groups', 'idx_sponsor_groups_listing', '(display_order, title)'],

    // The existing idx_sponsors_group covers the FK join; this also
    // covers the per-group ordering in modules/sponsors/sponsors.php.
    ['sponsors', 'idx_sponsors_listing', '(group_id, display_order, name)'],

    // modules/activity/index.php filters by module or action and always
    // orders by created_at DESC. Composite rather than two single-column
    // indexes so the sort is served by the index too — verified with
    // EXPLAIN, which dropped "Using filesort" once these replaced them.
    // This is the one table that grows without bound.
    ['activity_logs', 'idx_activity_module_time', '(module, created_at)'],
    ['activity_logs', 'idx_activity_action_time', '(action, created_at)'],

    // ORDER BY name  (modules/users/index.php)
    ['admin_users', 'idx_admin_users_name', '(name)'],

    // ORDER BY display_order, title (modules/updates/index.php)
    ['project_updates', 'idx_project_updates_listing', '(display_order, title)'],

    // ORDER BY category, display_order, name (modules/completed-projects/index.php)
    ['completed_projects', 'idx_completed_projects_listing', '(category, display_order, title)'],

    // ORDER BY display_order, title (modules/success-stories/index.php)
    ['success_stories', 'idx_success_stories_listing', '(display_order, title)'],

    // ORDER BY display_order, title (modules/video-gallery/index.php)
    ['video_gallery', 'idx_video_gallery_listing', '(display_order, title)'],

    // ORDER BY display_order, what_header (modules/mission-projects/index.php)
    ['mission_projects', 'idx_mission_projects_listing', '(display_order, what_header)'],

    // ORDER BY display_order, milestone_date (modules/impact-milestones/index.php)
    ['impact_milestones', 'idx_impact_milestones_listing', '(display_order, milestone_date)'],

    // ORDER BY display_order, title_highlight (modules/hero-slides/index.php)
    ['hero_slides', 'idx_hero_slides_listing', '(display_order, title_highlight)'],

    // ORDER BY display_order, name (modules/partners/index.php)
    ['partners', 'idx_partners_listing', '(display_order, name)'],
];

// NOTE: single-column (deleted_at) indexes used to be generated here, one per
// soft-deletable table. They are now supplied as the leading column of the
// idx_<t>_admin composites below and are listed in $redundant for removal.
// Generating them here as well would make every migration run create an index
// and then immediately drop it again.

/**
 * Phase 4 — composite indexes matching the query shapes that actually run.
 *
 * The single-column indexes above each cover one part of a query and nothing
 * covers the whole of it. MySQL picks exactly one index per table per query,
 * so a query like
 *
 *     WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, name
 *
 * used one of (status) / (deleted_at) / (display_order, name), then filtered
 * and re-sorted the remainder by hand. EXPLAIN before this change showed
 * "Using filesort" on all ten public content queries and a full table scan on
 * gallery_items, sponsors and sponsor_groups.
 *
 * Two shapes are needed per content table, because the two tiers ask different
 * questions:
 *
 *   _public — the visitor-facing read layer, which always filters
 *             status='active' AND deleted_at IS NULL. Leading with status is
 *             what lets the whole WHERE + ORDER BY be served from the index.
 *   _admin  — the CMS list, which shows drafts too and so filters only on
 *             deleted_at. Leading with deleted_at also serves the Trash page's
 *             "deleted_at IS NOT NULL", which is why the old single-column
 *             (deleted_at) index is dropped below rather than kept alongside.
 *
 * Column order within each is WHERE columns first, then ORDER BY columns in
 * their sort order — the only order that lets InnoDB satisfy both without a
 * sort step.
 */
$compositeIndexes = [
    // programs additionally orders by category, so it sits between the
    // equality columns and display_order.
    ['programs',        'idx_programs_public',        '(status, deleted_at, category, display_order, name)'],
    ['programs',        'idx_programs_admin',         '(deleted_at, category, display_order, name)'],

    ['team_members',    'idx_team_public',            '(status, deleted_at, display_order, name)'],
    ['team_members',    'idx_team_admin',             '(deleted_at, display_order, name)'],

    ['gallery_items',   'idx_gallery_public',         '(status, deleted_at, display_order, caption)'],
    ['gallery_items',   'idx_gallery_admin',          '(deleted_at, display_order, caption)'],

    ['annual_reports',  'idx_reports_public',         '(status, deleted_at, display_order, title)'],
    ['annual_reports',  'idx_reports_admin',          '(deleted_at, display_order, title)'],

    ['project_updates', 'idx_updates_public',         '(status, deleted_at, display_order, title)'],
    ['project_updates', 'idx_updates_admin',          '(deleted_at, display_order, title)'],

    ['sponsors',        'idx_sponsors_public',        '(status, deleted_at, display_order, name)'],
    // group_id second so this also serves "one group's sponsors, newest
    // ordering" without falling back to idx_sponsors_listing.
    ['sponsors',        'idx_sponsors_admin',         '(deleted_at, group_id, display_order, name)'],

    ['sponsor_groups',  'idx_sponsor_groups_public',  '(status, deleted_at, display_order, title)'],
    ['sponsor_groups',  'idx_sponsor_groups_admin',   '(deleted_at, display_order, title)'],

    // activity_logs is the one unbounded table. "ORDER BY created_at DESC
    // LIMIT n" with no WHERE currently scans the whole table — invisible at
    // 133 rows, quadratically annoying at a million.
    ['activity_logs',   'idx_activity_created_id',    '(created_at, id)'],
];

foreach ($compositeIndexes as $spec) {
    $indexes[] = $spec;
}

foreach ($indexes as [$table, $index, $indexColumns]) {
    if (!tableExists($db, $table)) {
        echo "  SKIP  {$table}.{$index} — table does not exist\n";
        $skipped++;
        continue;
    }

    if (indexExists($db, $table, $index)) {
        $skipped++;
        continue;
    }

    $sql = "CREATE INDEX {$index} ON {$table} {$indexColumns}";

    if ($dryRun) {
        echo "  WOULD RUN  {$sql}\n";
        $applied++;
        continue;
    }

    $db->exec($sql);
    echo "  CREATED  {$table}.{$index} {$indexColumns}\n";
    $applied++;
}

// Indexes made redundant by a wider one added later. Dropping them keeps
// write cost down; each is a strict prefix of a composite above, so the
// optimizer loses nothing.
$redundant = [
    ['activity_logs', 'idx_activity_module'],
    ['activity_logs', 'idx_activity_action'],

    // --- Phase 4 ---
    // Each of these is now a strict *prefix* of one of the composites added
    // above, which is the only condition under which dropping an index is
    // provably free: any query the narrow index could serve, the wider one
    // serves too, using the same leading columns. Verified by comparing column
    // lists programmatically rather than by eye.
    //
    // (status) — prefix of idx_<t>_public (status, deleted_at, ...)
    ['programs',        'idx_programs_status'],
    ['team_members',    'idx_team_status'],
    ['gallery_items',   'idx_gallery_status'],
    ['annual_reports',  'idx_reports_status'],
    ['project_updates', 'idx_project_updates_status'],

    // (deleted_at) — prefix of idx_<t>_admin (deleted_at, ...)
    ['programs',        'idx_programs_deleted'],
    ['team_members',    'idx_team_members_deleted'],
    ['gallery_items',   'idx_gallery_items_deleted'],
    ['annual_reports',  'idx_annual_reports_deleted'],
    ['project_updates', 'idx_project_updates_deleted'],
    ['sponsors',        'idx_sponsors_deleted'],
    ['sponsor_groups',  'idx_sponsor_groups_deleted'],

    // (created_at) — prefix of idx_activity_created_id (created_at, id)
    ['activity_logs',   'idx_activity_logs_created'],

    // These two were already redundant before this phase, against indexes that
    // have existed for a while — nothing had checked.
    // (page) — prefix of uq_content_blocks (page, block_key)
    ['content_blocks',  'idx_content_blocks_page'],
    // (group_id) — prefix of idx_sponsors_listing (group_id, display_order, name).
    // Dropping it is safe for the foreign key too: InnoDB requires *an* index
    // beginning with the FK column, and idx_sponsors_listing does.
    ['sponsors',        'idx_sponsors_group'],
];

$dropped = 0;
foreach ($redundant as [$table, $index]) {
    if (!tableExists($db, $table) || !indexExists($db, $table, $index)) {
        continue;
    }

    if ($dryRun) {
        echo "  WOULD RUN  DROP INDEX {$index} ON {$table}\n";
        $dropped++;
        continue;
    }

    $db->exec("DROP INDEX {$index} ON {$table}");
    echo "  DROPPED  {$table}.{$index} — superseded by a composite index\n";
    $dropped++;
}

printf(
    "\n%s: %d change%s %s, %d dropped as redundant, %d already present.\n",
    $dryRun ? 'Dry run' : 'Done',
    $applied,
    $applied === 1 ? '' : 's',
    $dryRun ? 'would be applied' : 'applied',
    $dropped,
    $skipped
);
