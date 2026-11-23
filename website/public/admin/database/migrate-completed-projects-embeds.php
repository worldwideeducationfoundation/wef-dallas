<?php
/**
 * One-off migration: completed_projects' image paths and raw-HTML embed
 * columns, on a database that already had the table before the
 * map_image_path/video_url redesign (see schema.sql's comment on this
 * table and CONTEXT.md §11).
 *
 * Three things, in order:
 *   1. Strip the "admin/uploads/" prefix baked into image_path/
 *      secondary_image_path/tertiary_image_path, so they match the
 *      relative-to-admin/uploads convention uploadUrl() expects (the
 *      convention every other content table already uses).
 *   2. Extract the <img src="..."> from map_embed and the <iframe src="...">
 *      from video_embed into the new map_image_path/video_url columns.
 *   3. Drop map_embed and video_embed — nothing reads them once this runs,
 *      and leaving live columns nobody reads is how a future admin form
 *      ends up wired back to the stored-XSS risk this whole change removed.
 *
 * Safe to run more than once: each step checks before acting.
 *
 * Usage: php admin/database/migrate-completed-projects-embeds.php [--apply]
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

function hasColumn(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c'
    );
    $stmt->execute(['t' => $table, 'c' => $column]);

    return (int) $stmt->fetchColumn() > 0;
}

if (!hasColumn($db, 'completed_projects', 'map_embed') && !hasColumn($db, 'completed_projects', 'video_embed')) {
    echo "Nothing to do — map_embed/video_embed are already gone.\n";
    exit(0);
}

$rows = $db->query('SELECT id, image_path, secondary_image_path, tertiary_image_path, map_embed, video_embed FROM completed_projects')->fetchAll();

$prefix = 'admin/uploads/';
$changes = 0;

foreach ($rows as $row) {
    $set = [];

    foreach (['image_path', 'secondary_image_path', 'tertiary_image_path'] as $col) {
        $value = $row[$col] ?? null;
        if ($value !== null && str_starts_with($value, $prefix)) {
            $set[$col] = substr($value, strlen($prefix));
        }
    }

    if (!empty($row['map_embed']) && preg_match('/src="([^"]+)"/', $row['map_embed'], $m)) {
        $mapPath = $m[1];
        if (str_starts_with($mapPath, $prefix)) {
            $mapPath = substr($mapPath, strlen($prefix));
        }
        $set['map_image_path'] = $mapPath;
    }

    if (!empty($row['video_embed']) && preg_match('/src="([^"]+)"/', $row['video_embed'], $m)) {
        $set['video_url'] = $m[1];
    }

    if ($set === []) {
        continue;
    }

    $changes++;
    echo "Project #{$row['id']}: " . implode(', ', array_map(
        static fn(string $k, string $v): string => "{$k} = {$v}",
        array_keys($set),
        array_values($set)
    )) . "\n";

    if ($apply) {
        $assignments = implode(', ', array_map(static fn(string $k): string => "{$k} = :{$k}", array_keys($set)));
        $stmt = $db->prepare("UPDATE completed_projects SET {$assignments} WHERE id = :id");
        $stmt->execute(array_merge($set, ['id' => $row['id']]));
    }
}

if ($apply) {
    if (hasColumn($db, 'completed_projects', 'map_embed')) {
        $db->exec('ALTER TABLE completed_projects DROP COLUMN map_embed');
        echo "Dropped column map_embed.\n";
    }
    if (hasColumn($db, 'completed_projects', 'video_embed')) {
        $db->exec('ALTER TABLE completed_projects DROP COLUMN video_embed');
        echo "Dropped column video_embed.\n";
    }
    echo "\nApplied: {$changes} row(s) updated.\n";
} else {
    echo "\nDry run: {$changes} row(s) would be updated, then map_embed/video_embed would be dropped. Re-run with --apply.\n";
}
