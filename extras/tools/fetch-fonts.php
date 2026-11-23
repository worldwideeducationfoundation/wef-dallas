<?php

declare(strict_types=1);

/**
 * Downloads the web fonts the site uses and writes a self-hosted stylesheet.
 *
 *     php tools/fetch-fonts.php
 *
 * Run it only when FAMILIES below changes. The output is committed, so a normal
 * deploy needs no network access and no build step.
 *
 * ---------------------------------------------------------------------------
 * Why self-host rather than link to Google
 * ---------------------------------------------------------------------------
 * The site was loading fonts through an @import at the top of styles.css. That
 * is the slowest of all the available options, because @import cannot be
 * discovered by the browser's preload scanner — it is only found after
 * styles.css has been fetched AND parsed. The full critical path was:
 *
 *     HTML  ->  styles.css  ->  fonts.googleapis.com/css2  ->  fonts.gstatic.com
 *
 * Four serial round trips, the last two to origins needing their own DNS
 * lookup, TCP connection and TLS handshake, before a single glyph could paint.
 * On a slow connection in Chitral — the audience this site is actually for —
 * that is most of a second of blank or fallback-font text.
 *
 * Self-hosting collapses all of it. The fonts come from the same origin and the
 * same already-open connection as the page, are discovered by the preload
 * scanner in the initial HTML, and are covered by the year-long immutable cache
 * rule already in public/.htaccess. It also removes two third-party origins
 * from the page, which is why the CSP can now say font-src 'self' with no
 * Google host in it at all.
 *
 * Licensing: Inter, Plus Jakarta Sans and Cinzel are all SIL Open Font License
 * 1.1, which explicitly permits redistribution and self-hosting. OFL.txt is
 * written alongside the fonts to satisfy the attribution requirement.
 */

/**
 * The stylesheets to generate, and the families each one declares.
 *
 * Two sheets rather than one because the public site and the admin have no
 * font in common: shipping a single combined file would make every visitor
 * parse @font-face rules for a typeface only the CMS uses, and vice versa.
 * They do share the font DIRECTORY, so 'prefix' is the path from each
 * stylesheet back to public/assets/fonts/.
 *
 * Weights are limited to what each stylesheet actually uses. The public
 * request previously asked for Inter 300 as well; a grep of styles.css finds
 * no font-weight below 400, so it was ~15 KB nobody ever saw. Cinzel was being
 * requested by our-mission.php alone, on a second stylesheet link that also
 * re-requested all five weights of Plus Jakarta Sans that styles.css had
 * already pulled in.
 */
const FONT_SHEETS = [
    'public/css/fonts.css' => [
        'prefix' => '../assets/fonts/',
        'families' => [
            'Inter' => [400, 500, 600],
            'Plus Jakarta Sans' => [400, 500, 600, 700, 800],
            'Cinzel' => [700, 800],
        ],
    ],
    'public/admin/assets/css/fonts.css' => [
        'prefix' => '../../../assets/fonts/',
        'families' => [
            'DM Sans' => [400, 500, 700],
        ],
    ],
];

/**
 * Subsets kept. Google serves cyrillic, cyrillic-ext, greek, greek-ext and
 * vietnamese alongside these; the site is English-only, so keeping them would
 * roughly triple the number of files for glyphs no page can render.
 */
const KEEP_SUBSETS = ['latin', 'latin-ext'];

/**
 * A modern browser UA. Google's CSS API serves ancient TTF to a UA it does not
 * recognise and woff2 — roughly 30% smaller — to one it does. PHP's default
 * user agent gets the TTF.
 */
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
    . '(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

$root = dirname(__DIR__, 2) . '/website';
$fontDir = $root . '/public/assets/fonts';

if (!is_dir($fontDir) && !mkdir($fontDir, 0755, true) && !is_dir($fontDir)) {
    fwrite(STDERR, "Could not create {$fontDir}\n");
    exit(1);
}

$downloaded = 0;
$reused = 0;
$skipped = 0;
$bytes = 0;

foreach (FONT_SHEETS as $relativeCssPath => $sheet) {
    printf("=== %s ===\n", $relativeCssPath);

    $query = [];
    foreach ($sheet['families'] as $family => $weights) {
        $query[] = 'family=' . str_replace('%20', '+', rawurlencode((string) $family))
            . ':wght@' . implode(';', $weights);
    }

    $url = 'https://fonts.googleapis.com/css2?' . implode('&', $query) . '&display=swap';

    $css = httpGet($url);
    if ($css === null) {
        fwrite(STDERR, "Could not fetch {$url}\n");
        exit(1);
    }

    // Each @font-face block is preceded by a /* subset */ comment, which is
    // the only place the subset name appears — the rule itself carries a
    // unicode-range but not a label.
    $blocks = preg_split('/(?=\/\*\s*[a-z-]+\s*\*\/)/', $css, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $out = [];

    foreach ($blocks as $block) {
        if (preg_match('/\/\*\s*([a-z-]+)\s*\*\//', $block, $m) !== 1) {
            continue;
        }

        $subset = $m[1];
        if (!in_array($subset, KEEP_SUBSETS, true)) {
            $skipped++;
            continue;
        }

        if (preg_match('/src:\s*url\((https:\/\/[^)]+\.woff2)\)/', $block, $m) !== 1) {
            continue;
        }

        $remote = $m[1];

        preg_match("/font-family:\s*'([^']+)'/", $block, $fm);
        preg_match('/font-weight:\s*(\d+)/', $block, $wm);

        $name = slug($fm[1] ?? 'font') . '-' . ($wm[1] ?? '400') . '-' . $subset . '.woff2';
        $target = $fontDir . '/' . $name;

        if (is_file($target)) {
            $reused++;
        } else {
            $data = httpGet($remote);
            if ($data === null) {
                fwrite(STDERR, "  ! failed: {$remote}\n");
                continue;
            }
            file_put_contents($target, $data);
            $downloaded++;
            printf("  + %-42s %d KB\n", $name, (int) round(strlen($data) / 1024));
        }

        $bytes += filesize($target);

        // Rewrite the absolute Google URL to a path relative to this
        // stylesheet, and keep the unicode-range so the browser still skips a
        // file whose glyphs the page never uses.
        $out[] = trim(str_replace($remote, $sheet['prefix'] . $name, $block));
    }

    if ($out === []) {
        fwrite(STDERR, "No @font-face rules extracted for {$relativeCssPath} — the response format may have changed.\n");
        exit(1);
    }

    $header = <<<CSS
    /* ============================================================================
     * Self-hosted web fonts — GENERATED FILE, DO NOT EDIT BY HAND.
     *
     * Regenerate with:  php tools/fetch-fonts.php
     * Source of truth:  the FONT_SHEETS constant in that script.
     *
     * Served from this origin rather than fonts.gstatic.com so the files ride the
     * connection the page has already opened, are found by the preload scanner in
     * the initial HTML, and fall under the immutable cache rule in
     * public/.htaccess. See the script's header comment for the full reasoning.
     *
     * font-display: swap is preserved from the upstream CSS: text paints
     * immediately in the fallback face and is swapped when the web font lands,
     * rather than being invisible while it downloads.
     *
     * Fonts are SIL Open Font License 1.1 — see assets/fonts/OFL.txt.
     * ========================================================================== */

    CSS;

    $target = $root . '/' . $relativeCssPath;
    $dir = dirname($target);

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "Could not create {$dir}\n");
        exit(1);
    }

    file_put_contents($target, $header . "\n" . implode("\n\n", $out) . "\n");
    printf("  wrote %d @font-face rules\n\n", count($out));
}

file_put_contents($fontDir . '/OFL.txt', <<<TXT
The fonts in this directory are licensed under the
SIL Open Font License, Version 1.1.

  Inter              (c) The Inter Project Authors — https://github.com/rsms/inter
  Plus Jakarta Sans  (c) Tokotype — https://github.com/tokotype/PlusJakartaSans
  Cinzel             (c) Natanael Gama — https://github.com/NDISCOVER/Cinzel
  DM Sans            (c) Colophon Foundry — https://github.com/googlefonts/dm-fonts

Full licence text: https://openfontlicense.org/open-font-license-official-text/

These files were retrieved from Google Fonts by tools/fetch-fonts.php and are
redistributed here unmodified, which the licence expressly permits.
TXT);

printf(
    "%d files downloaded, %d already present, %d subsets skipped, %d KB on disk.\n",
    $downloaded,
    $reused,
    $skipped,
    (int) round($bytes / 1024)
);

// ---------------------------------------------------------------------------

function httpGet(string $url): ?string
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: " . UA . "\r\nAccept: */*\r\n",
            'timeout' => 30,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);

    return $body === false ? null : $body;
}

function slug(string $value): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value);
}
