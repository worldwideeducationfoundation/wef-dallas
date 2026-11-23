<?php
/**
 * One-off: moves the hardcoded Annual Reports and Project Updates into the CMS.
 *
 * Both tables were empty while the pages rendered the content from markup, so
 * the two modules had complete editing screens that controlled nothing. This
 * reads the existing markup and inserts it, after which the templates can be
 * switched over without the page going blank.
 *
 * Idempotent: rows are matched on a natural key (pdf_path for reports, slug
 * for updates) and skipped if already present, so re-running is safe.
 *
 *   php admin/database/import-hardcoded-content.php --dry-run
 *   php admin/database/import-hardcoded-content.php --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/db.php';

$apply = in_array('--apply', $argv, true);
$root = dirname(__DIR__, 2);

/** Collapse whitespace and decode entities from markup that was hand-wrapped. */
$clean = static function (string $v): string {
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($v), ENT_QUOTES, 'UTF-8')) ?? $v);
};

// ---------------------------------------------------------------- reports ---
echo "=== annual_reports ===\n";
$reportsHtml = (string) file_get_contents($root . '/annual-reports.php');

preg_match_all(
    '#<div class="wef-doc-item.*?<h3 class="wef-doc-title">(.*?)</h3>\s*<p class="wef-doc-meta">(.*?)</p>.*?href="(assets/PDF/[^"]+)"#s',
    $reportsHtml,
    $reportMatches,
    PREG_SET_ORDER
);

$reportOrder = 0;
$reportsAdded = 0;
foreach ($reportMatches as $m) {
    $title = $clean($m[1]);
    $desc = $clean($m[2]);
    $pdf = $m[3];
    $reportOrder++;

    $exists = (int) dbFetchColumn('SELECT COUNT(*) FROM annual_reports WHERE pdf_path = :p', ['p' => $pdf], 0);
    if ($exists > 0) {
        printf("  skip (already present)  %s\n", mb_substr($title, 0, 58));
        continue;
    }

    printf("  %-22s %s\n", $apply ? 'insert' : 'would insert', mb_substr($title, 0, 58));
    if ($apply) {
        dbInsert(
            "INSERT INTO annual_reports (title, description, pdf_path, display_order, status)
             VALUES (:t, :d, :p, :o, 'active')",
            ['t' => $title, 'd' => $desc, 'p' => $pdf, 'o' => $reportOrder]
        );
    }
    $reportsAdded++;
}

// ---------------------------------------------------------------- updates ---
echo "\n=== project_updates ===\n";
$indexHtml = (string) file_get_contents($root . '/index.php');

// Scope to the Project Updates section so cards elsewhere cannot match.
$secStart = strpos($indexHtml, 'section project-updates');
$secEnd = strpos($indexHtml, '</section>', $secStart === false ? 0 : $secStart);
$section = $secStart === false ? '' : substr($indexHtml, $secStart, $secEnd - $secStart);

preg_match_all(
    '#<div class="updates-card">.*?<img[^>]*src="([^"]+)"[^>]*>.*?'
    . '<span class="updates-card-tag tag-(\w+)">(.*?)</span>.*?'
    . '<h3>(.*?)</h3>\s*<p>(.*?)</p>.*?'
    . '<a href="([^"]+)" class="updates-card-link"#s',
    $section,
    $cardMatches,
    PREG_SET_ORDER
);

printf("  parsed %d card(s) from index.php\n", count($cardMatches));

$slugify = static function (string $t): string {
    $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $t) ?? $t, '-'));
    return mb_substr($s, 0, 160);
};

$updateOrder = 0;
$updatesAdded = 0;
foreach ($cardMatches as $m) {
    $image = $m[1];
    $tag = $clean($m[3]);
    $title = $clean($m[4]);
    $content = $clean($m[5]);
    $link = $m[6];
    $slug = $slugify($title);
    $updateOrder++;

    $exists = (int) dbFetchColumn('SELECT COUNT(*) FROM project_updates WHERE slug = :s', ['s' => $slug], 0);
    if ($exists > 0) {
        printf("  skip (already present)  %s\n", mb_substr($title, 0, 56));
        continue;
    }

    printf("  %-22s [%-12s] %s\n", $apply ? 'insert' : 'would insert', $tag, mb_substr($title, 0, 46));
    if ($apply) {
        dbInsert(
            "INSERT INTO project_updates (title, slug, content, tag, link_url, image_path, display_order, status)
             VALUES (:t, :s, :c, :g, :l, :i, :o, 'active')",
            [
                't' => mb_substr($title, 0, 160),
                's' => $slug,
                'c' => $content,
                'g' => $tag,
                'l' => $link,
                // image_path is stored exactly as the markup referenced it. These
                // are repository assets under assets/images/, not CMS uploads, so
                // they are NOT rewritten to an admin/uploads/ path — doing that
                // would point every card at a file that does not exist.
                'i' => $image,
                'o' => $updateOrder,
            ]
        );
    }
    $updatesAdded++;
}

printf(
    "\n%s: %d report(s) and %d update(s) %s\n",
    $apply ? 'Applied' : 'Dry run',
    $reportsAdded,
    $updatesAdded,
    $apply ? 'inserted' : 'would be inserted'
);
