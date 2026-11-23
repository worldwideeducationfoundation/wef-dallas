<?php
/**
 * Idempotent seed: copies the 5 roadmap points out of
 * includes/static-data.php ('impact_milestones' key) into the real
 * impact_milestones table — same pattern as the other seed scripts.
 *
 * Usage: php admin/database/seed-impact-milestones.php [--apply]
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
$rows = $staticData['impact_milestones'] ?? [];

$table = 'impact_milestones';
$columns = ['milestone_date', 'centers_target', 'students_target', 'teachers_target', 'display_order', 'status'];

$existing = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
if ($existing > 0) {
    echo "{$table}: already has {$existing} row(s) — skipping (this script only seeds an empty table).\n";
    exit(0);
}

echo "{$table}: " . count($rows) . " row(s) to insert.\n";

if (!$apply) {
    foreach ($rows as $row) {
        echo '  WOULD INSERT: ' . $row['milestone_date'] . "\n";
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
    echo "  INSERTED: {$row['milestone_date']}\n";
}

echo "\nDone.\n";
