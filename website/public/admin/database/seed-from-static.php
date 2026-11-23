<?php
/**
 * One-time: copies the content the website is currently showing from
 * includes/static-data.php into the admin tables that are still empty.
 *
 * Why: every public reader in includes/content.php falls back to
 * static-data.php when its table has no rows. On a database where Team,
 * Gallery, Honor Board, Project Updates, Annual Reports or Settings were never
 * filled, the website looks complete while the admin shows "No items yet" -
 * and adding the first item in the admin replaces the whole fallback list
 * with that one item. Copying the rows in makes the admin show, and control,
 * exactly what visitors see.
 *
 * Rules:
 *   - A table is only filled if it has NO rows at all (trashed rows count).
 *     Anything already entered in the admin is never touched, and re-running
 *     is safe.
 *   - Rows keep their original ids, so sponsors stay attached to their
 *     honor-board groups, and keep their status / deleted_at, so a draft stays
 *     a draft and a trashed row goes to Trash rather than going live.
 *   - A file path whose file does not exist is stored as empty (with a
 *     warning) instead of as a broken image; upload the file in the admin.
 *
 *   php admin/database/seed-from-static.php            (dry run: report only)
 *   php admin/database/seed-from-static.php --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/content.php';

$apply = in_array('--apply', $argv, true);

$db = getDb();
if ($db === null) {
    fwrite(STDERR, "Could not connect to the database. Check config/config.php.\n");
    exit(1);
}

// sponsor_groups before sponsors: sponsors.group_id points at a group id.
$tables = [
    'site_settings',
    'team_members',
    'gallery_items',
    'sponsor_groups',
    'sponsors',
    'project_updates',
    'annual_reports',
    'programs',
    'success_stories',
    'video_gallery',
    'mission_projects',
    'impact_milestones',
    'hero_slides',
    'partners',
];

$fileColumns = ['image_path', 'cover_image_path', 'pdf_path', 'logo_path'];
$uploadsDir = dirname(__DIR__) . '/uploads/';
$publicDir = dirname(__DIR__, 2) . '/';

$existingTables = array_flip($db->query(
    'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()'
)->fetchAll(PDO::FETCH_COLUMN));

$totalInserted = 0;
$warnings = 0;

foreach ($tables as $table) {
    $rows = getStaticData($table);

    if (!isset($existingTables[$table])) {
        echo "SKIP  {$table}: table does not exist - run admin/database/migrate.php first\n";
        continue;
    }
    if ($rows === []) {
        echo "SKIP  {$table}: no built-in data\n";
        continue;
    }

    $count = (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    if ($count > 0) {
        echo "KEEP  {$table}: already has {$count} row(s) - left alone\n";
        continue;
    }

    $columns = array_flip($db->query(
        "SELECT column_name FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = " . $db->quote($table)
    )->fetchAll(PDO::FETCH_COLUMN));

    $inserted = 0;
    foreach ($rows as $row) {
        $row = array_intersect_key($row, $columns);
        $label = (string) ($row['name'] ?? $row['title'] ?? $row['caption'] ?? $row['title_highlight'] ?? ('#' . ($row['id'] ?? '?')));

        foreach ($fileColumns as $column) {
            $path = $row[$column] ?? null;
            if ($path === null || $path === '') {
                continue;
            }
            if (!is_file($uploadsDir . $path) && !is_file($publicDir . $path)) {
                $live = ($row['status'] ?? 'active') === 'active' && empty($row['deleted_at']);
                echo "  WARN  {$table} \"{$label}\": {$column} file {$path} is missing - stored empty"
                    . ($live ? '; upload it in the admin' : ' (not a live item)') . "\n";
                $row[$column] = null;
                $warnings++;
            }
        }

        if ($apply) {
            $names = array_keys($row);
            $sql = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $table,
                implode(', ', array_map(static fn(string $c): string => "`{$c}`", $names)),
                implode(', ', array_map(static fn(string $c): string => ":{$c}", $names))
            );
            $db->prepare($sql)->execute($row);
        }
        $inserted++;
    }

    echo ($apply ? 'FILL  ' : 'WOULD FILL  ') . "{$table}: {$inserted} row(s)\n";
    $totalInserted += $inserted;
}

echo "\n" . ($apply ? 'Done' : 'Dry run') . ": {$totalInserted} row(s) "
    . ($apply ? 'inserted' : 'would be inserted') . ", {$warnings} warning(s).\n";
if (!$apply && $totalInserted > 0) {
    echo "Nothing was written. Re-run with --apply to insert.\n";
}
