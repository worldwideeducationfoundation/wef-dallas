<?php
/**
 * One-time: copies the 16 completed-project records out of
 * includes/completed-projects-data.php (a PHP array that file's own header
 * comment calls "a mock database layer... once a backend developer creates
 * the real table, they can simply replace the array with a PDO fetch call")
 * into the real `completed_projects` table.
 *
 * The source file is left in place afterwards and becomes the FALLBACK data
 * path - the same role includes/static-data.php plays for every other
 * content type - rather than being deleted, so the two public pages that
 * use it keep working if the database is ever unreachable.
 *
 * Idempotent: matched on the unique `slug` column, existing rows are left
 * untouched. Safe to re-run.
 *
 *   php admin/database/seed-completed-projects.php --dry-run
 *   php admin/database/seed-completed-projects.php --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/completed-projects-data.php';

$apply = in_array('--apply', $argv, true);

$db = getDb();
if ($db === null) {
    fwrite(STDERR, "Could not connect to the database.\n");
    exit(1);
}

$projects = getCompletedProjects();
if (empty($projects)) {
    fwrite(STDERR, "No completed projects found - nothing to do.\n");
    exit(0);
}

$existing = $db->query('SELECT slug FROM completed_projects')->fetchAll(PDO::FETCH_COLUMN);
$existingSet = array_flip($existing);

$insert = $db->prepare(
    'INSERT INTO completed_projects
        (slug, title, summary, description, location, completion_year, category,
         image_path, secondary_image_path, tertiary_image_path, map_embed, video_embed,
         stat1_value, stat1_label, stat2_value, stat2_label, stat3_value, stat3_label,
         display_order, status)
     VALUES
        (:slug, :title, :summary, :description, :location, :completion_year, :category,
         :image_path, :secondary_image_path, :tertiary_image_path, :map_embed, :video_embed,
         :stat1_value, :stat1_label, :stat2_value, :stat2_label, :stat3_value, :stat3_label,
         :display_order, :status)'
);

$inserted = 0;
$skipped = 0;
$order = 0;

foreach ($projects as $p) {
    $order++;
    $slug = $p['slug'] ?? null;
    if ($slug === null) {
        continue;
    }

    if (isset($existingSet[$slug])) {
        echo "SKIP (already exists): {$slug}\n";
        $skipped++;
        continue;
    }

    echo ($apply ? 'INSERT' : 'WOULD INSERT') . ": {$slug} ({$p['title']})\n";

    if ($apply) {
        // The one stale --sp-4xl token reference (renamed to --space-24 in
        // the design-system pass) is corrected here rather than carried
        // into the database as a broken custom property.
        $videoEmbed = isset($p['video_embed'])
            ? str_replace('var(--sp-4xl)', 'var(--space-24)', $p['video_embed'])
            : null;

        $insert->execute([
            'slug' => $slug,
            'title' => $p['title'] ?? $slug,
            'summary' => $p['summary'] ?? '',
            'description' => $p['description'] ?? '',
            'location' => $p['location'] ?? null,
            'completion_year' => $p['completion_year'] ?? null,
            'category' => $p['category'] ?? null,
            'image_path' => $p['image_path'] ?? null,
            'secondary_image_path' => $p['secondary_image_path'] ?? null,
            'tertiary_image_path' => $p['tertiary_image_path'] ?? null,
            'map_embed' => $p['map_embed'] ?? null,
            'video_embed' => $videoEmbed,
            'stat1_value' => $p['stat1_value'] ?? null,
            'stat1_label' => $p['stat1_label'] ?? null,
            'stat2_value' => $p['stat2_value'] ?? null,
            'stat2_label' => $p['stat2_label'] ?? null,
            'stat3_value' => $p['stat3_value'] ?? null,
            'stat3_label' => $p['stat3_label'] ?? null,
            'display_order' => $order,
            'status' => 'active',
        ]);
    }

    $inserted++;
}

echo "\n" . ($apply ? 'Inserted' : 'Would insert') . ": {$inserted}. Skipped (already present): {$skipped}.\n";
if (!$apply) {
    echo "Re-run with --apply to write these rows.\n";
}
