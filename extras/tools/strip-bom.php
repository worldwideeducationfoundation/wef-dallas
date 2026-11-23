<?php

declare(strict_types=1);

/**
 * Removes byte-order marks — real and mangled — from PHP source.
 *
 *     php tools/strip-bom.php            (report)
 *     php tools/strip-bom.php --apply    (fix)
 *
 * ---------------------------------------------------------------------------
 * What it is fixing
 * ---------------------------------------------------------------------------
 * Two forms turned up in this codebase, and both are emitted to the browser
 * before <!DOCTYPE html>:
 *
 *   EF BB BF            A real UTF-8 BOM at the start of the file, ahead of
 *                       the opening "<?php". PHP treats anything before the
 *                       open tag as output and sends it.
 *
 *   C3 AF C2 BB C2 BF   The characters "ï»¿" — a UTF-8 BOM that was read as
 *                       Latin-1 by some editor and saved back as UTF-8. Nine
 *                       program pages carried one of these sitting between
 *                       "?>" and "<!DOCTYPE html>", where it is not a BOM at
 *                       all any more, just three visible characters.
 *
 * Why it matters, in descending order of severity:
 *
 *   1. Any header() call after that point fails with "headers already sent".
 *      That is a live functional hazard for redirects and cookies, not a
 *      cosmetic one.
 *   2. Content before the doctype can put a browser into quirks mode, which
 *      changes box sizing and layout.
 *   3. "ï»¿" renders as visible text at the top of the document and is read
 *      aloud by screen readers.
 *   4. An HTML parser sees character data before <html>, which is why an
 *      accessibility audit reported these pages as missing their lang
 *      attribute — the parser had already opened an implied element.
 *
 * ---------------------------------------------------------------------------
 * What it deliberately does not touch
 * ---------------------------------------------------------------------------
 * Only the two sequences above, and only where they sit at the start of the
 * file or immediately before the doctype. A "ï»¿" inside real page copy would
 * be a content bug for a human to look at, not something to silently delete.
 */

$apply = in_array('--apply', $argv, true);

$root = dirname(__DIR__, 2) . '/website';

/** Every PHP file in the project, excluding version control. */
$files = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $entry */
foreach ($it as $entry) {
    $path = str_replace('\\', '/', $entry->getPathname());

    if (!$entry->isFile() || strtolower($entry->getExtension()) !== 'php') {
        continue;
    }
    if (str_contains($path, '/.git/') || str_contains($path, '/node_modules/')) {
        continue;
    }

    $files[] = $entry->getPathname();
}

sort($files);

const BOM = "\xEF\xBB\xBF";
const MOJIBAKE = "\xC3\xAF\xC2\xBB\xC2\xBF";

$changed = 0;
$leading = 0;
$beforeDoctype = 0;

foreach ($files as $file) {
    $src = file_get_contents($file);
    $original = $src;
    $notes = [];

    // 1. Leading marks, in any number and either form.
    while (true) {
        if (str_starts_with($src, BOM)) {
            $src = substr($src, strlen(BOM));
            $notes[] = 'leading BOM';
            continue;
        }
        if (str_starts_with($src, MOJIBAKE)) {
            $src = substr($src, strlen(MOJIBAKE));
            $notes[] = 'leading mojibake';
            continue;
        }
        break;
    }

    // 2. Marks immediately before the doctype, whatever precedes them.
    $replaced = 0;
    $src = preg_replace(
        '/(?:' . preg_quote(BOM, '/') . '|' . preg_quote(MOJIBAKE, '/') . ')+(\s*<!DOCTYPE)/i',
        '$1',
        $src,
        -1,
        $replaced
    ) ?? $src;

    if ($replaced > 0) {
        $notes[] = "{$replaced} before doctype";
        $beforeDoctype += $replaced;
    }

    if ($notes === []) {
        continue;
    }

    if (str_contains(implode(' ', $notes), 'leading')) {
        $leading++;
    }

    $changed++;
    printf(
        "  %-52s %s\n",
        str_replace('\\', '/', substr($file, strlen($root) + 1)),
        implode(', ', $notes)
    );

    if ($apply && $src !== $original) {
        file_put_contents($file, $src);
    }
}

printf(
    "\n%d file(s): %d with a leading mark, %d mark(s) removed from before a doctype.\n",
    $changed,
    $leading,
    $beforeDoctype
);
echo $apply ? "Applied.\n" : "Pass --apply to fix.\n";

if (!$apply) {
    exit($changed > 0 ? 1 : 0);
}
