<?php
/**
 * Idempotent seed: copies the home page's 9 hero slides and 6 partner logos
 * out of includes/static-data.php ('hero_slides' / 'partners' keys - the
 * content that was hardcoded in index.php, verbatim) into their tables, so
 * the new Home Slideshow and Partner Logos admin modules start out holding
 * exactly what the site already shows.
 *
 * Each bundled image (public/assets/...) is copied into admin/uploads/hero/
 * or admin/uploads/partners/ with its size variants regenerated there, so
 * the rows point at uploads like every other module and an admin replacing
 * or deleting one never touches the bundled originals.
 *
 * Usage: php admin/database/seed-hero-and-partners.php [--apply]
 * (no flag = dry run)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/image-derivatives.php';

$apply = in_array('--apply', $argv, true);
$db = getDb();

if ($db === null) {
    fwrite(STDERR, "Could not connect to the database.\n");
    exit(1);
}

$staticData = require __DIR__ . '/../../../includes/static-data.php';
$publicDir = realpath(__DIR__ . '/../..');
$uploadsDir = $publicDir . '/admin/uploads';

/** Copies a bundled asset into uploads/{$folder}/ and returns the uploads-relative path. */
function copyIntoUploads(string $publicDir, string $uploadsDir, string $assetPath, string $folder, bool $apply): ?string
{
    $source = $publicDir . '/' . ltrim($assetPath, '/');
    if (!is_file($source)) {
        echo "  MISSING FILE: {$assetPath}\n";
        return null;
    }

    $relative = $folder . '/' . basename($source);
    if ($apply) {
        if (!is_dir($uploadsDir . '/' . $folder)) {
            mkdir($uploadsDir . '/' . $folder, 0755, true);
        }
        $target = $uploadsDir . '/' . $relative;
        if (!is_file($target)) {
            copy($source, $target);
        }
        copySizeVariants($source, $uploadsDir . '/' . $folder);
        generateImageDerivatives($target);
    }

    return $relative;
}

/**
 * Copies the bundled asset's existing size variants (name-480w.ext,
 * name.ext.webp, …) beside the upload. generateImageDerivatives() would
 * rebuild them, but it needs GD, which the CLI PHP often lacks even when
 * the web server's PHP has it; the bundled ones are already correct.
 */
function copySizeVariants(string $source, string $targetDir): void
{
    $dir = dirname($source);
    $ext = pathinfo($source, PATHINFO_EXTENSION);
    $base = pathinfo($source, PATHINFO_FILENAME);
    $variants = array_merge(
        glob($dir . '/' . $base . '-*w.' . $ext) ?: [],
        glob($dir . '/' . $base . '-*w.' . $ext . '.webp') ?: [],
        glob($dir . '/' . $base . '.' . $ext . '.webp') ?: []
    );
    foreach ($variants as $variant) {
        $to = $targetDir . '/' . basename($variant);
        if (!is_file($to)) {
            copy($variant, $to);
        }
    }
}

function seedRows(PDO $db, bool $apply, string $table, array $rows, array $columns, string $labelKey, callable $prepare): void
{
    $existing = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    if ($existing > 0) {
        echo "{$table}: already has {$existing} row(s) - skipping (this script only seeds an empty table).\n";
        return;
    }

    echo "{$table}: " . count($rows) . " row(s) to insert.\n";

    $placeholders = implode(', ', array_map(fn(string $c): string => ":{$c}", $columns));
    $stmt = $db->prepare("INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})");

    foreach ($rows as $row) {
        $row = $prepare($row);
        if (!$apply) {
            echo '  WOULD INSERT: ' . $row[$labelKey] . "\n";
            continue;
        }
        $params = [];
        foreach ($columns as $column) {
            $params[$column] = $row[$column] ?? null;
        }
        $stmt->execute($params);
        echo '  INSERTED: ' . $row[$labelKey] . "\n";
    }
}

seedRows(
    $db,
    $apply,
    'hero_slides',
    $staticData['hero_slides'] ?? [],
    ['title_lead', 'title_highlight', 'description', 'image_path', 'image_alt', 'display_order', 'status'],
    'title_highlight',
    function (array $row) use ($publicDir, $uploadsDir, $apply): array {
        $row['image_path'] = copyIntoUploads($publicDir, $uploadsDir, $row['image_src'], 'hero', $apply);
        return $row;
    }
);

seedRows(
    $db,
    $apply,
    'partners',
    $staticData['partners'] ?? [],
    ['name', 'logo_path', 'link_url', 'display_order', 'status'],
    'name',
    function (array $row) use ($publicDir, $uploadsDir, $apply): array {
        $row['logo_path'] = copyIntoUploads($publicDir, $uploadsDir, $row['logo_src'], 'partners', $apply);
        return $row;
    }
);

echo $apply ? "\nDone.\n" : "\nDry run - re-run with --apply to insert.\n";
