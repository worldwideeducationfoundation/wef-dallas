<?php

declare(strict_types=1);

/**
 * Extracts the above-the-fold subset of css/styles.css into css/critical.css.
 *
 *     php tools/build-critical-css.php
 *     php tools/build-critical-css.php --list-dropped   (audit what was cut)
 *
 * Re-run it after editing styles.css. The output is committed; the site never
 * runs this at request time.
 *
 * ---------------------------------------------------------------------------
 * What this is for
 * ---------------------------------------------------------------------------
 * styles.css is 162 KB and render-blocking: nothing paints until it has been
 * downloaded AND parsed. The critical subset is inlined into the <head> of
 * every page (see includes/layout/critical-css.php), so the header and hero
 * can paint from the HTML response alone, and the full stylesheet is then
 * fetched without blocking.
 *
 * ---------------------------------------------------------------------------
 * Why generated rather than hand-written
 * ---------------------------------------------------------------------------
 * A hand-maintained copy of "the important rules" is wrong the first time
 * somebody edits styles.css and forgets it, and the failure mode is a visible
 * flash of unstyled header on every page — the exact problem it was added to
 * prevent. Deriving it from the real stylesheet means the two cannot drift:
 * the only thing maintained by hand is the list of selector prefixes below,
 * which changes far less often than the rules themselves.
 *
 * The usual alternative is a headless browser that measures what actually
 * intersects the first viewport. That is more precise and would add Node, a
 * browser download and a lockfile to a project whose entire dependency list is
 * currently empty — a trade this file deliberately does not make. Being
 * slightly generous with what counts as critical costs a few KB inline; being
 * wrong in the other direction costs a flash of unstyled content, so where the
 * two conflict this errs towards including more.
 */

/**
 * Selector prefixes that count as above-the-fold.
 *
 * Derived from the markup that renders in the first viewport: the shared
 * header (includes/layout/site-header.php), the homepage hero, and the
 * .page-hero block every interior page opens with.
 */
const CRITICAL_PATTERNS = [
    // Design tokens and base element styling. Without :root every var() in
    // every kept rule resolves to nothing, so this one is non-negotiable.
    '/^:root$/',
    '/^\*$/',
    '/^(html|body)\b/',
    '/^h[1-6]\b/',
    '/^(p|a|img|svg|ul|ol|li|button|figure|picture)\b/',
    '/^\*\s*,/',

    // Shared layout primitives.
    '/^\.container\b/',
    '/^\.section\b/',

    // Site header and navigation — on screen before anything else, on every
    // page, and the one thing a visitor will notice reflowing.
    '/^\.header\b/',
    '/^\.header-logo\b/',
    '/^\.nav-/',
    '/^\.dropdown-/',
    '/^\.hamburger\b/',
    '/^\.mobile-overlay\b/',
    '/^\.skip-link\b/',

    // Homepage hero and the interior-page hero band.
    '/^\.hero-/',
    '/^\.hero\b/',
    '/^\.page-hero\b/',
    // Project pages (includes/layout/project-page.php): the cover photo band
    // with the project's name, and the drawn line under it.
    '/^\.project-page\b/',
    '/^\.pp-cover/',
    '/^\.pp-hl\b/',
    '/^\.pp-swoosh\b/',
    '/^\.anim-/',
    '/^\.static-part\b/',
    '/^\.highlight-part\b/',
    '/^\.typed-cursor\b/',
    '/^\.eyebrow-accent-line\b/',

    // Utilities that change layout or hide content. .sr-only in particular
    // must be inline: without it the visually-hidden <h1> on the homepage
    // renders full size until styles.css lands.
    '/^\.sr-only\b/',

    // Scroll-reveal classes, which start at opacity:0 and are switched on by
    // initScrollReveal() in main.js. These have to be inline even though the
    // elements they apply to are mostly below the fold, because leaving them
    // out inverts the sequence a visitor sees: the content paints visible from
    // the inline CSS, then styles.css arrives and hides it, then JavaScript
    // fades it back in. Hidden-then-revealed is correct; visible-then-hidden
    // -then-revealed is a flicker.
    '/^\.reveal\b/',
    '/^\.reveal-/',
    '/^\.no-underline\b/',
    '/^\.visually-hidden\b/',

    // .btn and its variants are deliberately NOT here. A grep of the header
    // partial and the first viewport of every page finds no .btn element above
    // the fold — the hero's own call-to-action is styled by
    // .hero-glass-actions, which is kept above. Including the button system
    // cost about 2 KB on every page load for rules nothing on screen used.
];

/**
 * Warn above this, measured GZIPPED.
 *
 * The raw size is the misleading number: this CSS is inlined into an HTML
 * response that the server compresses, so gzipped bytes are what a visitor
 * actually pays. 14 KB is the usual rule of thumb for staying inside the
 * initial congestion window along with the rest of the document.
 */
const SIZE_WARN_BYTES = 14 * 1024;

$root = dirname(__DIR__, 2) . '/website';
$sourcePath = $root . '/public/css/styles.css';
$targetPath = $root . '/public/css/critical.css';
$listDropped = in_array('--list-dropped', $argv, true);

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Not found: {$sourcePath}\n");
    exit(1);
}

$css = file_get_contents($sourcePath);

// Comments are stripped first so a brace or a selector inside one cannot be
// mistaken for structure by the parser below.
$css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;

$blocks = parseBlocks($css);

$kept = [];
$dropped = [];

foreach ($blocks as $block) {
    if ($block['type'] === 'at') {
        // @media / @supports: keep the wrapper only if something inside it
        // survives, so the output has no empty query blocks.
        if (!preg_match('/^@(media|supports)/i', $block['prelude'])) {
            continue;
        }

        $inner = parseBlocks($block['body']);
        $innerKept = [];

        foreach ($inner as $rule) {
            if ($rule['type'] === 'rule' && selectorIsCritical($rule['prelude'])) {
                $innerKept[] = $rule['prelude'] . '{' . trim($rule['body']) . '}';
            }
        }

        if ($innerKept !== []) {
            $kept[] = $block['prelude'] . '{' . implode('', $innerKept) . '}';
        }

        continue;
    }

    if ($block['type'] !== 'rule') {
        continue;
    }

    if (selectorIsCritical($block['prelude'])) {
        $kept[] = $block['prelude'] . '{' . trim($block['body']) . '}';
    } else {
        $dropped[] = $block['prelude'];
    }
}

// Animations are referenced by name from the rules above (the hero's Ken Burns
// effect, the nav transitions). A kept rule whose @keyframes were dropped
// simply does not animate, which reads as a broken hero rather than a missing
// optimisation — so pull in exactly the keyframes that are actually named.
$keptCss = implode('', $kept);
$animationNames = [];

if (preg_match_all('/animation(?:-name)?\s*:\s*([^;}]+)/i', $keptCss, $m) > 0) {
    foreach ($m[1] as $value) {
        foreach (preg_split('/[\s,]+/', trim($value)) ?: [] as $token) {
            if (preg_match('/^[a-z_][\w-]*$/i', $token) === 1) {
                $animationNames[$token] = true;
            }
        }
    }
}

$keyframes = [];

foreach ($blocks as $block) {
    if ($block['type'] !== 'at' || preg_match('/^@(-\w+-)?keyframes\s+([\w-]+)/i', $block['prelude'], $km) !== 1) {
        continue;
    }

    if (isset($animationNames[$km[2]])) {
        $keyframes[] = $block['prelude'] . '{' . trim($block['body']) . '}';
    }
}

$out = "/* GENERATED by tools/build-critical-css.php — do not edit.\n"
    . "   Above-the-fold subset of styles.css, inlined into <head>.\n"
    . "   Rebuild after changing styles.css. */\n"
    . $keptCss . implode('', $keyframes) . "\n";

// Whitespace only — no property rewriting, no shorthand collapsing. A
// minifier that misunderstands one value here would break the first thing a
// visitor sees, and the saving over gzip of doing more than this is marginal.
$out = preg_replace('/\s*\n\s*/', '', $out) ?? $out;
$out = preg_replace('/\s{2,}/', ' ', $out) ?? $out;
$out = str_replace([' {', '{ ', ' }', '; }', ': ', ' ,'], ['{', '{', '}', '}', ':', ','], $out);

file_put_contents($targetPath, $out);

$size = strlen($out);

printf(
    "%d rules kept, %d dropped, %d keyframe blocks pulled in.\n",
    count($kept),
    count($dropped),
    count($keyframes)
);
$gzipped = strlen(gzencode($out, 9));

printf(
    "Wrote public/css/critical.css — %d KB raw, %.1f KB gzipped.\n",
    (int) round($size / 1024),
    $gzipped / 1024
);
printf(
    "For comparison, styles.css is %d KB raw / %.1f KB gzipped, and is what\n"
    . "no longer has to arrive before the page can paint.\n",
    (int) round(filesize($sourcePath) / 1024),
    strlen(gzencode($css, 9)) / 1024
);

if ($gzipped > SIZE_WARN_BYTES) {
    printf(
        "\nNOTE: %.1f KB gzipped is above the %d KB guideline. Inlined CSS is paid\n"
        . "for on every page load and is never cached, so tighten CRITICAL_PATTERNS\n"
        . "if this keeps growing.\n",
        $gzipped / 1024,
        (int) round(SIZE_WARN_BYTES / 1024)
    );
}

if ($listDropped) {
    echo "\nDropped selectors:\n";
    foreach ($dropped as $selector) {
        echo '  ' . $selector . "\n";
    }
}

// ---------------------------------------------------------------------------

/**
 * Splits CSS into top-level blocks.
 *
 * Deliberately not a full CSS parser — it only needs to find the boundary
 * between one top-level rule and the next, which is a brace-depth count. It
 * tracks quotes so a brace inside a content: "…" string cannot desynchronise
 * the depth.
 *
 * @return list<array{type: string, prelude: string, body: string}>
 */
function parseBlocks(string $css): array
{
    $blocks = [];
    $length = strlen($css);
    $depth = 0;
    $start = 0;
    $preludeEnd = null;
    $quote = null;

    for ($i = 0; $i < $length; $i++) {
        $ch = $css[$i];

        if ($quote !== null) {
            if ($ch === $quote && ($i === 0 || $css[$i - 1] !== '\\')) {
                $quote = null;
            }
            continue;
        }

        if ($ch === '"' || $ch === "'") {
            $quote = $ch;
            continue;
        }

        if ($ch === '{') {
            if ($depth === 0) {
                $preludeEnd = $i;
            }
            $depth++;
            continue;
        }

        if ($ch !== '}') {
            continue;
        }

        $depth--;

        if ($depth !== 0 || $preludeEnd === null) {
            continue;
        }

        $prelude = trim(substr($css, $start, $preludeEnd - $start));
        $body = substr($css, $preludeEnd + 1, $i - $preludeEnd - 1);

        if ($prelude !== '') {
            $blocks[] = [
                'type' => str_starts_with($prelude, '@') ? 'at' : 'rule',
                'prelude' => $prelude,
                'body' => $body,
            ];
        }

        $start = $i + 1;
        $preludeEnd = null;
    }

    return $blocks;
}

/**
 * True when any comma-separated part of the selector is above the fold.
 *
 * Matching on any part rather than all of them is intentional: a rule written
 * as `.header, .footer { ... }` styles the header too, and dropping it because
 * .footer is below the fold would leave the header unstyled.
 *
 * Each part is normalised to its leftmost compound selector first, so
 * `.hero-glass .btn:hover` is tested as `.hero-glass` — a descendant selector
 * is only relevant if its ancestor is on screen.
 */
function selectorIsCritical(string $selector): bool
{
    foreach (explode(',', $selector) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        // Leftmost compound: stop at a combinator, a descendant space, or a
        // pseudo-class/element.
        $head = preg_split('/[\s>+~:\[]/', $part)[0] ?? $part;
        if ($head === '') {
            $head = $part;
        }

        foreach (CRITICAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $head) === 1) {
                return true;
            }
        }
    }

    return false;
}
