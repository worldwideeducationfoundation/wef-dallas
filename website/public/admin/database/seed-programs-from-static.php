<?php
/**
 * One-time: copies the 9 program-detail records already present in
 * includes/static-data.php (the fallback the site uses whenever the
 * database is unreachable or a query returns nothing) into the real
 * `programs` table.
 *
 * Why this is safe to write as a straight copy: static-data.php was
 * already prepared with complete, correct rows (slug, category, name,
 * summary, description, badge_text, location_text, all three stat
 * pairs, image_path) - it was just never inserted into the database the
 * public pages could actually read from, which is the whole reason the
 * program detail pages have been rendering hardcoded HTML instead of
 * database content. This does not invent any new data.
 *
 * Idempotent: matched on the unique `slug` column, existing rows are
 * left untouched. Safe to re-run.
 *
 *   php admin/database/seed-programs-from-static.php --dry-run
 *   php admin/database/seed-programs-from-static.php --apply
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
    fwrite(STDERR, "Could not connect to the database.\n");
    exit(1);
}

// publishedOnly() excludes anything not status=active/deleted_at=NULL at
// export time - in particular, one row of garbage test content that was
// soft-deleted in whatever database this snapshot came from but still
// carries status='active', which would otherwise get migrated in as a
// real, live, public program.
$programs = publishedOnly(getStaticData('programs'));
if (empty($programs)) {
    fwrite(STDERR, "No programs found in static-data.php - nothing to do.\n");
    exit(0);
}

$existing = $db->query('SELECT slug FROM programs')->fetchAll(PDO::FETCH_COLUMN);
$existingSet = array_flip($existing);

$insert = $db->prepare(
    'INSERT INTO programs
        (slug, category, name, summary, description, badge_text, location_text,
         year_launched, stat1_value, stat1_label, stat2_value, stat2_label,
         stat3_value, stat3_label, image_path, display_order, status)
     VALUES
        (:slug, :category, :name, :summary, :description, :badge_text, :location_text,
         :year_launched, :stat1_value, :stat1_label, :stat2_value, :stat2_label,
         :stat3_value, :stat3_label, :image_path, :display_order, :status)'
);

$inserted = 0;
$skipped = 0;

foreach ($programs as $p) {
    $slug = $p['slug'] ?? null;
    if ($slug === null) {
        continue;
    }

    if (isset($existingSet[$slug])) {
        echo "SKIP (already exists): {$slug}\n";
        $skipped++;
        continue;
    }

    echo ($apply ? 'INSERT' : 'WOULD INSERT') . ": {$slug} ({$p['name']})\n";

    if ($apply) {
        $insert->execute([
            'slug' => $slug,
            'category' => $p['category'] ?? 'programs',
            'name' => $p['name'] ?? $slug,
            'summary' => $p['summary'] ?? '',
            'description' => $p['description'] ?? '',
            'badge_text' => $p['badge_text'] ?? null,
            'location_text' => $p['location_text'] ?? null,
            'year_launched' => $p['year_launched'] ?? null,
            'stat1_value' => $p['stat1_value'] ?? null,
            'stat1_label' => $p['stat1_label'] ?? null,
            'stat2_value' => $p['stat2_value'] ?? null,
            'stat2_label' => $p['stat2_label'] ?? null,
            'stat3_value' => $p['stat3_value'] ?? null,
            'stat3_label' => $p['stat3_label'] ?? null,
            'image_path' => $p['image_path'] ?? null,
            'display_order' => $p['display_order'] ?? 0,
            'status' => $p['status'] ?? 'active',
        ]);
    }

    $inserted++;
}

echo "\n" . ($apply ? 'Inserted' : 'Would insert') . ": {$inserted}. Skipped (already present): {$skipped}.\n";
if (!$apply) {
    echo "Re-run with --apply to write these rows.\n";
}
