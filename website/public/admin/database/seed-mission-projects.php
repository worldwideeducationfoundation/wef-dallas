<?php
/**
 * Idempotent seed: copies the 9 rows of the "WEF At A Glance" table out of
 * includes/static-data.php ('mission_projects' key) into the real
 * mission_projects table — same pattern as seed-success-stories-and-videos.php.
 *
 * The source rows are the exact 9 <tr> blocks that used to be hardcoded on
 * our-mission.php, transcribed verbatim (financial figures, dashes and
 * apostrophes copied as-is rather than normalized).
 *
 * Usage: php admin/database/seed-mission-projects.php [--apply]
 * (no flag = dry run)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/database.php';

$apply = in_array('--apply', $argv, true);
$db = getDb();

if ($db === null) {
    fwrite(STDERR, "Could not connect to the database.\n");
    exit(1);
}

$staticData = require __DIR__ . '/../../../includes/static-data.php';
$rows = $staticData['mission_projects'] ?? [];

$table = 'mission_projects';
$columns = ['what_header', 'what_sub', 'launch_year', 'where_text', 'who_text', 'impact_text', 'notes_text', 'notes_highlight', 'display_order', 'status'];

$existing = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
if ($existing > 0) {
    echo "{$table}: already has {$existing} row(s) — skipping (this script only seeds an empty table).\n";
    exit(0);
}

echo "{$table}: " . count($rows) . " row(s) to insert.\n";

if (!$apply) {
    foreach ($rows as $row) {
        echo '  WOULD INSERT: ' . $row['what_header'] . "\n";
    }
    echo "\nDry run — re-run with --apply to insert.\n";
    exit(0);
}

$placeholders = implode(', ', array_map(fn(string $c): string => ":{$c}", $columns));
$columnList = implode(', ', $columns);
$stmt = $db->prepare("INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})");

foreach ($rows as $row) {
    $params = [];
    foreach ($columns as $column) {
        $params[$column] = $row[$column] ?? null;
    }
    $stmt->execute($params);
    echo "  INSERTED: {$row['what_header']}\n";
}

echo "\nDone.\n";
