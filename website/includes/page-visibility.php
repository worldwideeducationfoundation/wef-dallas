<?php
/**
 * Pages that can be switched off in the admin (admin -> Pages On/Off).
 *
 * A page that is off:
 *   - is left out of the header navigation (site-header.php);
 *   - answers "Page not found" with a 404 when visited (head-meta.php calls
 *     enforcePageVisibility());
 *   - has every link to it elsewhere on the site (footer, buttons, text)
 *     turned into plain text by an output filter (startHiddenPageLinkFilter(),
 *     started from bootstrap.php).
 *
 * The switch is pages.is_enabled (1 = on, the default). A page with no row
 * is on. If the column is missing (a database the update has not reached
 * yet) or the database is down, every page counts as on, so the site never
 * hides pages by accident.
 */

/**
 * The switchable pages, in header order: slug => label shown in the admin.
 * The home page is not here: it cannot be switched off.
 */
const SWITCHABLE_PAGES = [
    'our-team' => 'Our Team',
    'our-mission' => 'Our Mission',
    'success-stories' => 'Success Stories',
    'impact-goals' => 'Impact Goals',
    'annual-reports' => 'Annual Reports',
    'active-projects' => 'Active Projects',
    'completed-projects' => 'Completed Projects',
    'gallery' => 'Photo Gallery',
    'video-gallery' => 'Video Gallery',
    'contact' => 'Contact (Get in Touch)',
    'volunteer' => 'Volunteer',
    'donate' => 'Donate',
];

/**
 * Page files that belong to a switchable page and go off with it.
 */
const PAGE_VISIBILITY_FOLLOWERS = [
    'completed-project-details' => 'completed-projects',
];

/**
 * Slugs of the pages that are switched off. One query per request.
 *
 * @return list<string>
 */
function disabledPages(): array
{
    static $disabled = null;
    if ($disabled !== null) {
        return $disabled;
    }

    $disabled = [];
    try {
        require_once __DIR__ . '/database.php';
        $db = getDb();
        if ($db !== null) {
            $rows = $db->query('SELECT page_key FROM pages WHERE is_enabled = 0')->fetchAll(PDO::FETCH_COLUMN);
            $disabled = array_values(array_intersect(array_keys(SWITCHABLE_PAGES), $rows));
        }
    } catch (Throwable $e) {
        // Column or table missing, or no database: every page stays on.
        $disabled = [];
    }

    return $disabled;
}

function pageEnabled(string $slug): bool
{
    $slug = PAGE_VISIBILITY_FOLLOWERS[$slug] ?? $slug;

    return !in_array($slug, disabledPages(), true);
}

/**
 * The slug of the public page this request is rendering ("our-team"), or
 * null for anything else: the admin, the API, the command line.
 */
function currentPublicPageSlug(): ?string
{
    if (PHP_SAPI === 'cli') {
        return null;
    }

    $script = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $public = realpath(__DIR__ . '/../public');
    if ($script === false || $public === false || dirname($script) !== $public) {
        return null;
    }

    return basename($script, '.php');
}

/**
 * Called from the shared <head> (head-meta.php): a switched-off page is
 * replaced by the site's "Page not found", with a 404 status.
 */
function enforcePageVisibility(): void
{
    static $done = false;
    $slug = currentPublicPageSlug();
    if ($done || $slug === null || pageEnabled($slug)) {
        return;
    }
    $done = true;

    // Throw away the part of the page already written into the buffers
    // (bootstrap.php and the link filter), then start the link filter again
    // for the not-found page.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    startHiddenPageLinkFilter(true);

    http_response_code(404);
    $seoNoIndex = true;
    $seoTitle = 'Page not found — Worldwide Education Foundation';
    $seoDescription = 'The page you were looking for is not here.';
    require __DIR__ . '/layout/not-found.php';
    exit;
}

/**
 * Buffers a public page and turns every link to a switched-off page into
 * plain text. Does nothing when every page is on, or outside the public site.
 */
function startHiddenPageLinkFilter(bool $force = false): void
{
    static $started = false;
    if (($started && !$force) || currentPublicPageSlug() === null || disabledPages() === []) {
        return;
    }
    $started = true;

    ob_start('disableLinksToHiddenPages');
}

/**
 * Output filter: <a href="our-team">Our Team</a> becomes
 * <span class="link-disabled" aria-disabled="true">Our Team</span> for each
 * switched-off page. Matches the site's own relative links ("our-team",
 * "./our-team", "our-team.php", with or without ?query or #anchor); the
 * element keeps its class and id, so its styling and any script looking it
 * up still find it.
 */
function disableLinksToHiddenPages(string $html): string
{
    $slugs = disabledPages();
    foreach (PAGE_VISIBILITY_FOLLOWERS as $follower => $page) {
        if (in_array($page, $slugs, true)) {
            $slugs[] = $follower;
        }
    }
    if ($slugs === []) {
        return $html;
    }

    $alternatives = implode('|', array_map(fn($s) => preg_quote($s, '#'), $slugs));
    $pattern = '#<a\b([^>]*?)\shref=(["\'])(?:\./)?(?:' . $alternatives . ')(?:\.php)?(?:[?\#][^"\']*)?\2([^>]*)>(.*?)</a>#is';

    $result = preg_replace_callback($pattern, function (array $m): string {
        $attributes = $m[1] . ' ' . $m[3];
        $class = preg_match('#\sclass=(["\'])(.*?)\1#is', ' ' . $attributes, $c) ? trim($c[2]) . ' ' : '';
        $id = preg_match('#\sid=(["\'])(.*?)\1#is', ' ' . $attributes, $i) ? ' id="' . $i[2] . '"' : '';

        return '<span class="' . $class . 'link-disabled"' . $id . ' aria-disabled="true">' . $m[4] . '</span>';
    }, $html);

    // A regex failure (backtrack limit on a huge page) leaves the page as it was.
    return $result ?? $html;
}
