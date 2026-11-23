<?php

declare(strict_types=1);

/**
 * Repairs double-encoded UTF-8 ("mojibake") in source files.
 *
 *     php tools/fix-mojibake.php            (report, with before/after)
 *     php tools/fix-mojibake.php --apply    (fix)
 *
 * ---------------------------------------------------------------------------
 * What went wrong
 * ---------------------------------------------------------------------------
 * At some point these files were read as Windows-1252 and saved back as UTF-8.
 * Every non-ASCII character therefore became the sequence of Latin-1-looking
 * characters that its UTF-8 bytes happen to spell:
 *
 *     —  (em dash, U+2014, bytes E2 80 94)   became   â€"
 *     📍 (pin,     U+1F4CD, bytes F0 9F 93 8D) became  ðŸ“
 *
 * This is not cosmetic. The em dash appears inside <title> and inside
 * $seoTitle, which feeds og:title and twitter:title — so the mangled text is
 * what shows in the browser tab, in Google's results, and on every shared
 * link. Screen readers announce it character by character.
 *
 * ---------------------------------------------------------------------------
 * How the repair works
 * ---------------------------------------------------------------------------
 * The damage is exactly UTF8(CP1252(original)). Reversing it means encoding
 * the text back to CP1252 — which turns each of those characters into the
 * single byte it came from — and reading the result as UTF-8 again.
 *
 * The round trip is verified per file before anything is written:
 *
 *   1. The candidate result must be valid UTF-8.
 *   2. Re-applying the forward transformation to the result must reproduce the
 *      input exactly. If it does not, this file is not simply double-encoded
 *      and is left alone rather than guessed at.
 *
 * Only files that actually contain a known mojibake signature are touched, so
 * a file containing legitimately typed accented text is never rewritten.
 */

$apply = in_array('--apply', $argv, true);
$root = dirname(__DIR__, 2) . '/website';

/**
 * The characters CP1252 can represent above plain ASCII.
 *
 * Detection works on a counting argument rather than a list of known-bad
 * sequences, because a list is always incomplete: mis-decoding turns ONE real
 * character into the two-to-four CP1252 characters that spell its UTF-8 bytes,
 * so a genuinely double-encoded file always contains strictly more of these
 * characters than its repaired form. A file with legitimately typed accented
 * text does not shrink under the transformation and is therefore never
 * rewritten.
 *
 * Written as codepoints rather than literals so this file's own encoding
 * cannot affect the comparison.
 */
const CP1252_HIGH = [
    0x20AC, 0x201A, 0x0192, 0x201E, 0x2026, 0x2020, 0x2021, 0x02C6,
    0x2030, 0x0160, 0x2039, 0x0152, 0x017D, 0x2018, 0x2019, 0x201C,
    0x201D, 0x2022, 0x2013, 0x2014, 0x02DC, 0x2122, 0x0161, 0x203A,
    0x0153, 0x017E, 0x0178,
];

$files = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $entry */
foreach ($it as $entry) {
    $path = str_replace('\\', '/', $entry->getPathname());

    if (!$entry->isFile()) {
        continue;
    }
    if (!in_array(strtolower($entry->getExtension()), ['php', 'html', 'css', 'js', 'json', 'xml', 'md'], true)) {
        continue;
    }
    if (str_contains($path, '/.git/') || str_contains($path, '/node_modules/')) {
        continue;
    }

    // tools/ is excluded wholesale. These scripts document the byte sequences
    // they exist to remove, so their comments contain literal mojibake and
    // literal BOMs on purpose — "repairing" tools/strip-bom.php would replace
    // the "ï»¿" in its explanation with an invisible BOM character inside the
    // source. A developer tool that describes an encoding bug is not an
    // instance of one.
    if (str_contains($path, '/tools/')) {
        continue;
    }

    $files[] = $entry->getPathname();
}

sort($files);

$fixed = 0;
$skipped = 0;
$totalChars = 0;

foreach ($files as $file) {
    $src = file_get_contents($file);

    if (!mb_check_encoding($src, 'UTF-8')) {
        continue;
    }

    $before = countHigh($src);

    // Nothing above ASCII means nothing to repair.
    if ($before === 0) {
        continue;
    }

    $repaired = repair($src);

    if ($repaired === null || $repaired === $src) {
        continue;
    }

    // The counting test is applied per segment inside repair(); this is the
    // whole-file restatement of it, and a file that fails it here means the
    // segment logic let something through.
    if (countHigh($repaired) >= $before) {
        $skipped++;
        printf(
            "  ?  %-48s round-trips but does not shrink — treated as genuine text\n",
            relative($file, $root)
        );
        continue;
    }

    $fixed++;
    $delta = mb_strlen($src) - mb_strlen($repaired);
    $totalChars += $delta;

    printf("\n  %s  (%d characters recovered)\n", relative($file, $root), $delta);

    foreach (samples($src) as $was => $now) {
        printf("      %-46s ->  %s\n", $was, $now);
    }

    if ($apply) {
        file_put_contents($file, $repaired);
    }
}

printf("\n%d file(s) repaired, %d skipped as ambiguous, %d mojibake characters collapsed.\n",
    $fixed, $skipped, $totalChars);
echo $apply ? "Applied.\n" : "Pass --apply to fix.\n";

// ---------------------------------------------------------------------------

/**
 * Reverses one round of UTF-8 -> CP1252 mis-decoding, or returns null when the
 * text is not cleanly double-encoded.
 */
function repair(string $text): ?string
{
    // Run-wise, not whole-file and not even segment-wise.
    //
    // Mojibake always occupies a contiguous run of non-ASCII characters, and
    // the surrounding markup is pure ASCII. Repairing each run on its own is
    // what makes this safe on a mixed file: index.php contains correctly
    // encoded characters ("→", "✕") AND six mangled emoji, and any coarser
    // unit fails on the former and therefore leaves the latter unrepaired.
    //
    // A run is only replaced when it round-trips exactly and collapses, so a
    // run of legitimately typed accented text is copied through untouched.
    $changed = false;

    $out = preg_replace_callback(
        '/[^\x00-\x7F]+/u',
        static function (array $m) use (&$changed): string {
            $run = $m[0];
            $fixed = repairSegment($run);

            if ($fixed === null || countHigh($fixed) >= countHigh($run)) {
                return $run;
            }

            $changed = true;

            return $fixed;
        },
        $text
    );

    if ($out === null) {
        return null;
    }

    return $changed ? $out : null;
}

/** One run with no un-mappable characters in it. */
function repairSegment(string $text): ?string
{
    $bytes = toCp1252Bytes($text);

    if ($bytes === null || !mb_check_encoding($bytes, 'UTF-8')) {
        return null;
    }

    // Verification: re-applying the damage must reproduce the input exactly.
    // Without this a run that merely resembles mojibake could be corrupted.
    if (fromCp1252Bytes($bytes) !== $text) {
        return null;
    }

    return $bytes;
}

function isCp1252Representable(string $ch): bool
{
    $cp = mb_ord($ch, 'UTF-8');

    if ($cp === false) {
        return false;
    }
    if (in_array($cp, cp1252Map(), true)) {
        return true;
    }

    return $cp <= 0xFF;
}

/**
 * The 27 byte values where CP1252 differs from Latin-1, as byte => codepoint.
 *
 * The five bytes CP1252 leaves undefined (0x81, 0x8D, 0x8F, 0x90, 0x9D) are
 * deliberately absent: editors map those to the matching C1 control
 * codepoints, so they fall through to the Latin-1 identity mapping below.
 * That detail is why iconv cannot be used here — it rejects U+008D outright,
 * and these files contain it.
 *
 * @return array<int, int>
 */
function cp1252Map(): array
{
    static $map = [
        0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E,
        0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6,
        0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
        0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C,
        0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
        0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
        0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
    ];

    return $map;
}

/** Text -> the byte string it would be if written as CP1252, or null. */
function toCp1252Bytes(string $text): ?string
{
    $reverse = array_flip(cp1252Map());
    $out = '';

    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
        $cp = mb_ord($ch, 'UTF-8');

        if ($cp === false) {
            return null;
        }

        if (isset($reverse[$cp])) {
            $out .= chr($reverse[$cp]);
            continue;
        }

        // Latin-1 identity range, including the C1 controls CP1252 leaves
        // undefined. Anything above U+00FF has no single-byte form, which
        // means this text is not purely double-encoded.
        if ($cp <= 0xFF) {
            if (in_array($cp, cp1252Map(), true)) {
                // e.g. U+2014 is reachable only via 0x97; a bare U+00xx that
                // collides with a mapped codepoint would be ambiguous.
                return null;
            }
            $out .= chr($cp);
            continue;
        }

        return null;
    }

    return $out;
}

/** The forward transformation: CP1252 bytes -> text. */
function fromCp1252Bytes(string $bytes): string
{
    $map = cp1252Map();
    $out = '';

    for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
        $b = ord($bytes[$i]);

        if ($b < 0x80) {
            $out .= $bytes[$i];
            continue;
        }

        $out .= mb_chr($map[$b] ?? $b, 'UTF-8');
    }

    return $out;
}

/** Number of characters in the CP1252-above-ASCII repertoire. */
function countHigh(string $text): int
{
    $n = 0;

    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
        $cp = mb_ord($ch, 'UTF-8');
        if ($cp === false) {
            continue;
        }
        if (($cp >= 0xA0 && $cp <= 0xFF) || in_array($cp, CP1252_HIGH, true)) {
            $n++;
        }
    }

    return $n;
}

/**
 * A few concrete before/after pairs, for eyeballing the diff.
 *
 * @return array<string, string>
 */
function samples(string $before): array
{
    $out = [];

    if (preg_match_all('/\S*[\x{00A0}-\x{024F}\x{2000}-\x{27BF}\x{1F300}-\x{1FAFF}]\S*/u', $before, $m) === 0) {
        return $out;
    }

    foreach (array_slice(array_unique($m[0]), 0, 4) as $token) {
        $fixedToken = repair($token);
        $out[mb_substr($token, 0, 44)] = $fixedToken ?? '(only valid in context)';
    }

    return $out;
}

function relative(string $path, string $root): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}
