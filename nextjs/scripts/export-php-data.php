<?php
/**
 * Exports the PHP site's built-in data (content-block registry, SEO defaults,
 * static fallback content, completed projects, donate config, page lists) to
 * JSON under src/data/, so the Next.js site uses exactly the same defaults.
 *
 *   php scripts/export-php-data.php [path-to-php-website]
 *
 * Re-run only if the PHP site's data files change. The PHP site is the source
 * while both exist; once it is retired these JSON files are the source.
 */
$site = rtrim($argv[1] ?? __DIR__ . '/../../website', '/' . chr(92));
if (!is_file("$site/includes/content-blocks.php")) {
    fwrite(STDERR, "PHP site not found at $site\n");
    exit(1);
}
define('DISABLE_DATABASE', true);
require "$site/includes/content-blocks.php";
require_once "$site/includes/program-nav.php";
require_once "$site/includes/page-visibility.php";
require_once "$site/includes/completed-projects-data.php";
require_once "$site/includes/donate/config.php";

$out = __DIR__ . '/../src/data';
$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
$write = static function (string $name, $data) use ($out, $flags): void {
    file_put_contents("$out/$name.json", json_encode($data, $flags) . "\n");
    echo "wrote src/data/$name.json\n";
};

$write('block-registry', blockRegistry());
$write('seo-page-defaults', SEO_PAGE_DEFAULTS);
$write('block-limits', BLOCK_LIMITS);
$write('static-data', require "$site/includes/static-data.php");
$write('completed-projects', getCompletedProjects());
$write('pages', [
    'switchable' => SWITCHABLE_PAGES,
    'followers' => PAGE_VISIBILITY_FOLLOWERS,
    'programHubs' => PROGRAM_HUBS,
    'programJourney' => PROGRAM_JOURNEY,
    'programJourneyFallbackNames' => PROGRAM_JOURNEY_FALLBACK_NAMES,
]);
$write('donate-config', donateConfig());

// Programme pages: each public/<slug>.php sets $projectPage then includes the
// shared layout. Read the array without running the layout.
$projectPages = [];
foreach (glob("$site/public/*.php") as $file) {
    $src = file_get_contents($file);
    if (!str_contains($src, "layout/project-page.php")) {
        continue;
    }
    $code = preg_replace('#^\s*require[^;]*project-page\.php[\'"]\s*;\s*$#m', '', $src);
    $code = preg_replace('#^<\?php#', '', $code);
    $projectPage = null;
    eval($code);
    $projectPages[$projectPage['slug']] = $projectPage;
}
$write('project-pages', $projectPages);
