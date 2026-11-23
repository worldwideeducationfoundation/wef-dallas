<?php
/**
 * One-time fill of map_centers (admin -> Map Centers) from the GeoJSON file
 * the home page map used to read directly, public/assets/data/
 * locations_map1.geojson. Only runs while the table is empty, so it can
 * never overwrite centers edited in the admin.
 *
 *   php seed-map-centers.php            dry run: shows what would be added
 *   php seed-map-centers.php --apply    adds them
 *
 * The district comes from the end of each village name ("... Lower
 * Chitral"), the same rule the map used; sno becomes the display order.
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

$existing = (int) $db->query('SELECT COUNT(*) FROM map_centers')->fetchColumn();
if ($existing > 0) {
    echo "map_centers already has {$existing} rows - nothing to do.\n";
    exit(0);
}

$file = __DIR__ . '/../../assets/data/locations_map1.geojson';
$data = json_decode((string) file_get_contents($file), true);
if (!is_array($data['features'] ?? null)) {
    fwrite(STDERR, "Could not read {$file}\n");
    exit(1);
}

$models = ['Entrepreneurial Model', 'Government School', 'Private School', 'Semi Govt School'];
$insert = $db->prepare(
    'INSERT INTO map_centers (lead_name, sponsor, village, district, center_model, latitude, longitude, display_order, status)
     VALUES (:lead, :sponsor, :village, :district, :model, :lat, :lng, :ord, \'active\')'
);

$added = 0;
$skipped = [];
foreach ($data['features'] as $i => $feature) {
    $p = $feature['properties'] ?? [];
    [$lng, $lat] = $feature['geometry']['coordinates'] ?? [null, null];
    $village = trim((string) ($p['village'] ?? ''));
    $model = trim((string) ($p['centerModel'] ?? ''));
    $district = preg_match('/\b(upper|lower)\s+chitral\b/i', $village, $m)
        ? (strtolower($m[1]) === 'upper' ? 'Upper Chitral' : 'Lower Chitral')
        : null;

    if (!is_numeric($lat) || !is_numeric($lng) || $village === '' || $district === null || !in_array($model, $models, true)) {
        $skipped[] = '#' . ($p['sno'] ?? $i + 1) . ' ' . $village;
        continue;
    }

    if ($apply) {
        $insert->execute([
            'lead' => trim((string) ($p['name'] ?? '')) ?: null,
            'sponsor' => trim((string) ($p['sponsor'] ?? '')) ?: 'Unknown Sponsor',
            'village' => $village,
            'district' => $district,
            'model' => $model,
            'lat' => round((float) $lat, 6),
            'lng' => round((float) $lng, 6),
            'ord' => (int) ($p['sno'] ?? $i + 1),
        ]);
    }
    $added++;
}

echo ($apply ? 'Added' : 'Would add') . " {$added} centers.\n";
if ($skipped) {
    echo 'Skipped (missing coordinates, district or type): ' . implode('; ', $skipped) . "\n";
}
