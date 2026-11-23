<?php

declare(strict_types=1);

/**
 * Build-time image optimiser. Generates the derivative files the site serves;
 * it never modifies or deletes a source image.
 *
 * Run it after adding or replacing any image outside the CMS:
 *
 *     php -d extension=gd tools/optimize-images.php
 *     php -d extension=gd tools/optimize-images.php --force    (rebuild all)
 *     php -d extension=gd tools/optimize-images.php --dry-run  (report only)
 *
 * The `-d extension=gd` is needed on this XAMPP install because gd is
 * commented out in php.ini. Production (Hostinger) has it on by default, and
 * nothing the *site* serves needs gd at all — only this script and the CMS
 * upload path do, which is the point of doing the work here rather than
 * per-request.
 *
 * Images uploaded through the admin get the same treatment automatically at
 * upload time (see public/admin/includes/upload.php), so in normal operation
 * this only needs running for files committed to the repository.
 *
 * ---------------------------------------------------------------------------
 * What it produces, and why in this shape
 * ---------------------------------------------------------------------------
 *
 * 1. A WebP twin beside every raster source:
 *
 *        programs/model-schools-01.jpg  ->  programs/model-schools-01.jpg.webp
 *
 *    The extension is APPENDED, not replaced. `model-schools-01.webp` would be
 *    the prettier name and is the wrong one here: this tree already contains
 *    bright-beginnings-01.webp as a genuine, independent source file, so
 *    replacing the extension risks a generated file colliding with a real one
 *    and silently overwriting it. Appending cannot collide.
 *
 *    Nothing in the markup references these. public/.htaccess serves them by
 *    content negotiation when the browser's Accept header includes image/webp,
 *    so all 98 <img> tags and every CSS background-image get WebP without a
 *    single markup change — and a browser that cannot read WebP still gets the
 *    original file from the same URL.
 *
 * 2. Width variants for images wider than the largest breakpoint they are
 *    displayed at, plus a WebP twin of each:
 *
 *        model-schools-01-768w.jpg
 *        model-schools-01-768w.jpg.webp
 *
 *    These are what srcset points at (see imageSrcset() in includes/assets.php).
 *    srcset lists the .jpg URLs only; content negotiation upgrades each
 *    candidate to WebP on its own, so the markup stays half the size and stays
 *    readable.
 *
 * A derivative is skipped when it is already newer than its source, so a rerun
 * after changing one photo re-encodes that photo and nothing else.
 *
 * The encoding itself lives in includes/image-derivatives.php, shared with the
 * CMS upload path so the two cannot drift apart.
 */

require_once dirname(__DIR__, 2) . '/website/includes/image-derivatives.php';

/**
 * Directories scanned, relative to the project root.
 *
 * icons/ is included for the raster files produced by tools/derasterize-svg.php.
 * True .svg icons in there are skipped by extension, as they should be — an SVG
 * is already the compact form and has nothing to gain from a WebP twin.
 */
const SCAN_DIRS = [
    'public/assets/images',
    'public/assets/icons',
    'public/admin/uploads',
];

$root = dirname(__DIR__, 2) . '/website';
$force = in_array('--force', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);

if (!imageDerivativesAvailable()) {
    fwrite(STDERR, "gd with WebP support is required.\n");
    fwrite(STDERR, "Try:  php -d extension=gd tools/optimize-images.php\n");
    exit(1);
}

$scanned = 0;
$written = 0;
$sourceBytes = 0;
$webpBytes = 0;

foreach (SCAN_DIRS as $dir) {
    $abs = $root . '/' . $dir;
    if (!is_dir($abs)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $ext = strtolower($file->getExtension());

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }
        if (isImageDerivative($path) || $file->getSize() < DERIVATIVE_MIN_SOURCE_BYTES) {
            continue;
        }

        $scanned++;
        $rel = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');

        if ($dryRun) {
            printf("  + %s\n", $rel);
            continue;
        }

        $count = generateImageDerivatives($path, $force);
        $written += $count;

        $twin = $path . '.webp';

        if ($ext !== 'webp' && is_file($twin)) {
            $before = $file->getSize();
            $after = filesize($twin);
            $sourceBytes += $before;
            $webpBytes += $after;

            if ($count > 0) {
                printf(
                    "  + %-52s %7s -> %7s  (-%.0f%%)\n",
                    $rel,
                    human($before),
                    human($after),
                    (1 - $after / $before) * 100
                );
            }
        } elseif ($count > 0) {
            printf("  + %s\n", $rel);
        }
    }
}

printf("\n%d sources scanned, %d derivative files written.\n", $scanned, $written);

if ($sourceBytes > 0) {
    printf(
        "full-size WebP twins: %s -> %s  (%.0f%% smaller)\n",
        human($sourceBytes),
        human($webpBytes),
        (1 - $webpBytes / $sourceBytes) * 100
    );
}

if ($dryRun) {
    echo "\n(dry run — nothing was written)\n";
}

function human(int $bytes): string
{
    return $bytes >= 1048576
        ? sprintf('%.1f MB', $bytes / 1048576)
        : sprintf('%d KB', (int) round($bytes / 1024));
}
