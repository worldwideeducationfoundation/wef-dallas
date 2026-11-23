<?php
/**
 * The learning centers for the home page map, as GeoJSON - the same shape
 * js/map.js used to read from assets/data/locations_map1.geojson, now built
 * from the map_centers table so admin -> Map Centers edits appear on the
 * map. Read-only; only active, non-trashed centers are published.
 *
 * Falls back to the static file (which the table was first filled from) if
 * the database is unreachable or the table is empty, so the map never goes
 * blank because of a database hiccup.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/geo+json; charset=utf-8');
// No cache header of its own: public/.htaccess sends "no-cache" for every
// .php response, so an edit in the admin shows on the next page load.

$features = [];
try {
    $db = getDb();
    if ($db !== null) {
        $rows = $db->query(
            "SELECT lead_name, sponsor, village, district, center_model, latitude, longitude, display_order
               FROM map_centers
              WHERE status = 'active' AND deleted_at IS NULL
              ORDER BY display_order, id"
        )->fetchAll();
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $row['longitude'], (float) $row['latitude']],
                ],
                'properties' => [
                    'name' => (string) ($row['lead_name'] ?? ''),
                    'sponsor' => (string) $row['sponsor'],
                    'village' => (string) $row['village'],
                    'district' => (string) $row['district'],
                    'centerModel' => (string) $row['center_model'],
                    'sno' => (int) $row['display_order'],
                ],
            ];
        }
    }
} catch (Throwable $e) {
    $features = [];
}

if ($features === []) {
    readfile(__DIR__ . '/../assets/data/locations_map1.geojson');
    exit;
}

echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
