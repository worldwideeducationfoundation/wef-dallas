<?php
/**
 * One-off backfill: assigns each existing team member to the section they
 * already appear under on the public our-team.php page.
 *
 * Run once, after migrate.php has added team_members.section. Safe to run
 * again — it only writes rows whose section still holds the column
 * default, so a member moved deliberately in the admin afterwards is
 * never reverted.
 *
 * Reads the section membership out of our-team.php rather than hardcoding
 * 25 names here, so it reflects what the site actually shows today.
 *
 *   php admin/database/backfill-team-sections.php [--dry-run]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/database.php';

$dryRun = in_array('--dry-run', $argv, true);
$db = getDb();

$page = __DIR__ . '/../../our-team.php';
if (!is_file($page)) {
    fwrite(STDERR, "our-team.php not found — nothing to read sections from.\n");
    exit(1);
}

$html = file_get_contents($page);

// Split on the section headings, then collect the <h3> names that follow
// each one until the next heading.
$parts = preg_split(
    '~<h2>(Advisory Board Members|Chitral Team|Collaborating Partners)</h2>~',
    $html,
    -1,
    PREG_SPLIT_DELIM_CAPTURE
);

$sectionOf = [];
for ($i = 1; $i < count($parts); $i += 2) {
    $section = $parts[$i];
    preg_match_all('~<h3>([^<]+)</h3>~', $parts[$i + 1], $matches);
    foreach ($matches[1] as $name) {
        $sectionOf[html_entity_decode(trim($name), ENT_QUOTES, 'UTF-8')] = $section;
    }
}

printf("Found %d names across %d sections in our-team.php\n\n", count($sectionOf), 3);

$rows = $db->query('SELECT id, name, section FROM team_members ORDER BY display_order, name')->fetchAll();
$update = $db->prepare('UPDATE team_members SET section = :section WHERE id = :id');

$matched = 0;
$unmatched = [];

foreach ($rows as $row) {
    $name = trim($row['name']);

    if (!isset($sectionOf[$name])) {
        $unmatched[] = $name;
        continue;
    }

    $target = $sectionOf[$name];
    if ($row['section'] === $target) {
        $matched++;
        continue;
    }

    if ($dryRun) {
        printf("  WOULD SET  %-26s -> %s\n", $name, $target);
    } else {
        $update->execute(['section' => $target, 'id' => $row['id']]);
        printf("  SET  %-26s -> %s\n", $name, $target);
    }
    $matched++;
}

printf("\n%d of %d rows matched.\n", $matched, count($rows));

if ($unmatched !== []) {
    printf("\n%d row(s) had no matching name on the page and kept the default section:\n", count($unmatched));
    foreach ($unmatched as $name) {
        echo "  - {$name}\n";
    }
    echo "Set these manually in the admin if they belong elsewhere.\n";
}

// Names on the page with no DB row — usually content added to the HTML
// but never entered into the CMS, which is exactly what this migration
// is meant to surface.
$dbNames = array_map(fn(array $r): string => trim($r['name']), $rows);
$orphans = array_diff(array_keys($sectionOf), $dbNames);
if ($orphans !== []) {
    printf("\n%d name(s) appear on the page but have no CMS record:\n", count($orphans));
    foreach ($orphans as $name) {
        echo "  - {$name}\n";
    }
}
