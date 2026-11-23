<?php
/**
 * Prints the SHA-256 hashes of every inline script the site allows, and
 * checks that the Content-Security-Policy headers in the two .htaccess files
 * list exactly those.
 *
 * Neither policy allows 'unsafe-inline' for scripts, so an inline <script>
 * runs only if its exact bytes hash to a value in the policy. Run this after
 * editing any of them:
 *
 *   php extras/tools/csp-hashes.php
 *
 * Public site: INLINE_SCRIPT_JS_CLASS and INLINE_SCRIPT_ASYNC_CSS in
 *              website/includes/assets.php -> website/public/.htaccess
 *              (the <meta> copy in head-meta.php computes its own).
 * Admin:       every <script>…</script> without src in the files below
 *              -> website/public/admin/.htaccess
 *
 * Exits 1 when a policy is out of date, so it can also run as a check.
 */

$site = dirname(__DIR__, 2) . '/website';
require $site . '/includes/assets.php';

$hash = static fn(string $js): string => "'sha256-" . base64_encode(hash('sha256', $js, true)) . "'";

$groups = [
    'public/.htaccess' => [INLINE_SCRIPT_JS_CLASS, INLINE_SCRIPT_ASYNC_CSS],
    'public/admin/.htaccess' => [],
];
foreach (['public/admin/includes/layout-header.php', 'public/admin/modules/newsletter/callback.php'] as $file) {
    preg_match_all('#<script>(.*?)</script>#s', (string) file_get_contents($site . '/' . $file), $m);
    array_push($groups['public/admin/.htaccess'], ...$m[1]);
}

$stale = false;
foreach ($groups as $htaccess => $scripts) {
    $expected = array_map($hash, $scripts);
    preg_match('#Content-Security-Policy "[^"]*script-src ([^;"]*)#', (string) file_get_contents($site . '/' . $htaccess), $m);
    preg_match_all("#'sha256-[^']+'#", $m[1] ?? '', $found);
    $ok = $found[0] == $expected;
    $stale = $stale || !$ok;

    echo ($ok ? 'ok     ' : 'UPDATE ') . "website/$htaccess\n";
    echo "       script-src 'self' " . implode(' ', $expected) . ($htaccess === 'public/.htaccess' ? ' https://unpkg.com' : '') . "\n";
}

exit($stale ? 1 : 0);
