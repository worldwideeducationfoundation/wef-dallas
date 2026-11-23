<?php
/**
 * Rebuilds public/assets/data/locations_map1.geojson from a CSV export of
 * the ECLC centers list (columns: S.No, Candidate Name, Sponsor name,
 * Village, Center model, Center Enrollment, Lat, Long).
 *
 * The CSV is the authoritative source for this data - this is a full
 * replace of the GeoJSON's features, not a merge. Run again whenever the
 * client sends an updated export.
 *
 * Usage:
 *   php tools/import-map-centers.php path/to/centers.csv --dry-run
 *   php tools/import-map-centers.php path/to/centers.csv --apply
 *
 * --dry-run (default if neither flag is passed) prints what would be
 * written and reports every skipped/corrected row without touching the
 * GeoJSON file. --apply writes it.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$csvPath = $argv[1] ?? null;
$apply = in_array('--apply', $argv, true);

if ($csvPath === null || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php tools/import-map-centers.php path/to/centers.csv [--dry-run|--apply]\n");
    exit(1);
}

// Center model spellings found in real client exports, normalized to the
// 4 exact strings public/js/map.js's categoryColors keys on - anything
// that doesn't match one of those 4 exactly is silently dropped by the
// map's own applyFilters() (`if (!categoryColors[item.category]) return;`),
// so a typo here would make a marker fetch successfully but never render
// and never be caught by anything short of a manual pin count.
const MODEL_MAP = [
    'Entrepreneurial Model' => 'Entrepreneurial Model',
    'Entrepeneurial Model' => 'Entrepreneurial Model',
    'Private School' => 'Private School',
    'Private school' => 'Private School',
    'Government School' => 'Government School',
    'Governamen School' => 'Government School',
    'Elementry school' => 'Elementary School',
    'Elementary School' => 'Elementary School',
    'Elementary school' => 'Elementary School',
    'Eementry School' => 'Elementary School',
];

/**
 * A handful of source rows carry a coordinate like "35.5848,71" - two
 * spreadsheet cells that got concatenated during export, with the real
 * value as the numeric prefix before the stray comma. PHP's (float) cast
 * already stops at the first non-numeric character, which happens to
 * recover exactly that prefix - not a guess, just what the cast does.
 */
function parseCoord(string $raw): ?float
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    return (float) $raw;
}

$rows = array_map('str_getcsv', file($csvPath));
$header = array_map('trim', array_shift($rows));

$features = [];
$skippedNoCoords = [];
$correctedCoords = [];
$sno = 0;

foreach ($rows as $row) {
    if (count($row) < count($header) || trim(implode('', $row)) === '') {
        continue; // trailing blank line
    }
    $r = array_combine($header, $row);

    $rawLat = $r['Lat'] ?? '';
    $rawLong = $r['Long'] ?? '';
    $lat = parseCoord($rawLat);
    $lon = parseCoord($rawLong);

    $candidateName = trim((string) ($r['Candidate Name'] ?? ''));
    $sourceRow = (int) ($r['S.No'] ?? 0);

    if ($lat === null || $lon === null || $lat === 0.0 || $lon === 0.0) {
        $skippedNoCoords[] = "row {$sourceRow} ({$candidateName}): no coordinates";
        continue;
    }
    if (str_contains($rawLat, ',') || str_contains($rawLong, ',')) {
        $correctedCoords[] = "row {$sourceRow} ({$candidateName}): \"{$rawLat}\" / \"{$rawLong}\" -> {$lat}, {$lon}";
    }

    $rawModel = trim((string) ($r['Center model'] ?? ''));
    $model = MODEL_MAP[$rawModel] ?? null;
    if ($model === null) {
        fwrite(STDERR, "Unrecognized Center model \"{$rawModel}\" on row {$sourceRow} ({$candidateName}) - add it to MODEL_MAP and re-run.\n");
        exit(1);
    }

    $sno++;
    $features[] = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'Point',
            'coordinates' => [$lon, $lat],
        ],
        'properties' => [
            'name' => $candidateName,
            'sponsor' => trim((string) ($r['Sponsor name'] ?? '')),
            'village' => trim((string) ($r['Village'] ?? '')),
            'centerModel' => $model,
            'sno' => $sno,
        ],
    ];
}

$geojson = [
    'type' => 'FeatureCollection',
    'features' => $features,
];

$json = json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

echo "Parsed " . (count($rows)) . " CSV rows -> " . count($features) . " plottable centers.\n\n";

if ($skippedNoCoords !== []) {
    echo "Skipped (no coordinates, " . count($skippedNoCoords) . "):\n";
    foreach ($skippedNoCoords as $line) {
        echo "  - {$line}\n";
    }
    echo "\n";
}
if ($correctedCoords !== []) {
    echo "Coordinates recovered from a malformed cell (" . count($correctedCoords) . "):\n";
    foreach ($correctedCoords as $line) {
        echo "  - {$line}\n";
    }
    echo "\n";
}

$outPath = __DIR__ . '/../../website/public/assets/data/locations_map1.geojson';

if (!$apply) {
    echo "Dry run - nothing written. Re-run with --apply to write " . realpath($outPath) . "\n";
    exit(0);
}

file_put_contents($outPath, $json . "\n");
echo "Wrote {$outPath} (" . count($features) . " features).\n";
