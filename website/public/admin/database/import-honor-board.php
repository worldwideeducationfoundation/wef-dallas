<?php
/**
 * One-off: copies the hardcoded honor board on index.php into the CMS.
 *
 * The Sponsors module has held 47 sponsors for a while and no public page ever
 * read them — index.php rendered a hardcoded copy instead. Switching the page
 * over is the goal, but the two sources had drifted: 27 sponsors in the CMS had
 * an empty badge_text while the page displayed values like "04 Centers" and
 * "50 Centers". Flipping the template first would have silently deleted that
 * information from the live site.
 *
 * So the data moves before the template does. This reads the badge next to each
 * sponsor name in index.php and writes it to the matching CMS row, but ONLY
 * where the CMS badge is currently empty — an admin who has deliberately
 * changed a badge in the CMS is never overwritten by the old page.
 *
 * CLI only, idempotent, and --dry-run by default in the sense that it reports
 * every change before applying. Safe to re-run.
 *
 *   php admin/database/import-honor-board.php --dry-run
 *   php admin/database/import-honor-board.php --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/db.php';

$apply = in_array('--apply', $argv, true);
$projectRoot = dirname(__DIR__, 2);

$html = file_get_contents($projectRoot . '/index.php');
if ($html === false) {
    fwrite(STDERR, "Cannot read index.php\n");
    exit(1);
}

// Isolate the honor board so sponsor tiles elsewhere on the page cannot match.
$start = strpos($html, 'honor-board-section');
$end = strpos($html, '</section>', $start === false ? 0 : $start);
if ($start === false || $end === false) {
    fwrite(STDERR, "Could not locate the honor board section in index.php\n");
    exit(1);
}
$section = substr($html, $start, $end - $start);

preg_match_all(
    '#<span class="sponsor-name">(.*?)</span>\s*<span class="sponsor-badge-tag">(.*?)</span>#s',
    $section,
    $matches,
    PREG_SET_ORDER
);

/** Names differ by punctuation and entities between the page and the DB. */
$normalise = static function (string $value): string {
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
    $value = str_replace(['&', '’', '‘', '“', '”', '–', '—'], ['and', "'", "'", '"', '"', '-', '-'], $value);
    $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? $value;

    return trim(preg_replace('/\s+/', ' ', strtolower($value)) ?? $value);
};

$pageBadges = [];
foreach ($matches as $m) {
    $name = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')) ?? '');
    $badge = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8')) ?? '');
    if ($name !== '' && $badge !== '') {
        $pageBadges[$normalise($name)] = $badge;
    }
}

printf("Found %d sponsor tiles with a badge on index.php\n\n", count($pageBadges));

$sponsors = dbFetchAll(
    'SELECT s.id, s.name, s.badge_text, g.title AS group_title
       FROM sponsors s
       JOIN sponsor_groups g ON g.id = s.group_id
      WHERE s.deleted_at IS NULL
      ORDER BY g.display_order, s.display_order'
);

$filled = 0;
$already = 0;
$unmatched = [];

foreach ($sponsors as $s) {
    $current = trim((string) $s['badge_text']);
    if ($current !== '') {
        $already++;
        continue;
    }

    $key = $normalise((string) $s['name']);
    if (!isset($pageBadges[$key])) {
        $unmatched[] = $s['name'];
        continue;
    }

    printf("  %-52s  ->  %s\n", mb_substr($s['name'], 0, 52), $pageBadges[$key]);
    if ($apply) {
        dbExecute('UPDATE sponsors SET badge_text = :b WHERE id = :id', ['b' => $pageBadges[$key], 'id' => $s['id']]);
    }
    $filled++;
}

printf("\n%s: %d badge(s) %s, %d already set, %d with no match on the page\n",
    $apply ? 'Applied' : 'Dry run',
    $filled,
    $apply ? 'written' : 'would be written',
    $already,
    count($unmatched)
);

if ($unmatched) {
    echo "\nNo badge on the page for these (they will simply render without one):\n";
    foreach ($unmatched as $n) {
        echo '  - ' . mb_substr($n, 0, 66) . "\n";
    }
}
