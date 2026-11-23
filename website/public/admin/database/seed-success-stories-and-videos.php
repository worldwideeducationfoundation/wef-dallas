<?php
/**
 * Idempotent seed: copies the 6 success-story rows and 2 video rows out of
 * includes/static-data.php ('success_stories' / 'video_gallery' keys) into
 * their real tables — the same migration this codebase already did for
 * programs and completed_projects, following the identical pattern.
 *
 * The source rows are the exact cards that used to be hardcoded on
 * success-stories.php / video-gallery.php, preserved as-is (see the
 * comment on publishedSuccessStories() in includes/content.php for why the
 * placeholder-quality title casing is kept rather than cleaned up).
 *
 * Usage: php admin/database/seed-success-stories-and-videos.php [--apply]
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

function seedTable(PDO $db, bool $apply, string $table, array $rows, array $columns): void
{
    $existing = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    if ($existing > 0) {
        echo "{$table}: already has {$existing} row(s) — skipping (this script only seeds an empty table).\n";
        return;
    }

    echo "{$table}: " . count($rows) . " row(s) to insert.\n";

    if (!$apply) {
        foreach ($rows as $row) {
            echo '  WOULD INSERT: ' . $row['title'] . "\n";
        }
        return;
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
        echo "  INSERTED: {$row['title']}\n";
    }
}

seedTable(
    $db,
    $apply,
    'success_stories',
    $staticData['success_stories'] ?? [],
    ['title', 'tag', 'story_date', 'image_path', 'link_url', 'display_order', 'status']
);

seedTable(
    $db,
    $apply,
    'video_gallery',
    $staticData['video_gallery'] ?? [],
    ['title', 'youtube_url', 'display_order', 'status']
);

echo $apply ? "\nDone.\n" : "\nDry run — re-run with --apply to insert.\n";
