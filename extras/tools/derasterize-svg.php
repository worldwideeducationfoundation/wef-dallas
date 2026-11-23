<?php

declare(strict_types=1);

/**
 * Converts fake SVGs — a base64 raster image wrapped in an <svg> element — into
 * real raster files at the size they are actually displayed.
 *
 *     php -d extension=gd tools/derasterize-svg.php            (report)
 *     php -d extension=gd tools/derasterize-svg.php --apply    (convert)
 *
 * ---------------------------------------------------------------------------
 * The problem this exists for
 * ---------------------------------------------------------------------------
 * Four files in assets/icons/SVG/ are named .svg, are served as image/svg+xml,
 * and contain no vector data at all:
 *
 *     <svg width="3840" height="3024" ...>
 *       <image width="3840" height="3024" xlink:href="data:image/png;base64,..."/>
 *     </svg>
 *
 * That is a 3840x3024 PNG, base64-encoded — which inflates it by a further
 * third — displayed by the homepage at 100x100 CSS pixels. Between them they
 * were 945 KB of the 965 KB icon directory, for four icons whose rendered area
 * is smaller than this comment.
 *
 * They are almost certainly a design-tool export where "save as SVG" was
 * chosen for a layer that was a bitmap. Nothing about them benefits from being
 * SVG: they do not scale, they cannot be recoloured, and they cost more than
 * the photographs on the same page.
 *
 * ---------------------------------------------------------------------------
 * What the conversion does
 * ---------------------------------------------------------------------------
 * Decodes the embedded image, resamples the long edge down to MAX_EDGE, and
 * writes a real .png beside the original. tools/optimize-images.php then
 * generates the .webp twin, and public/.htaccess serves that to any browser
 * that accepts it — so the delivered file ends up smaller again.
 *
 * The original .svg is left on disk. Removing it is a separate decision, and
 * one that should follow a check that no page still points at it; this script
 * only ever adds a file.
 */

require_once dirname(__DIR__, 2) . '/website/includes/image-derivatives.php';

/**
 * Longest edge of the output, in pixels.
 *
 * The largest of these renders at 100x100 (`.impact-card-icon` sets
 * font-size: 100px and the tag sets width/height: 1em). 256 covers that at 2x
 * device pixel ratio with room to spare, and is still ~200 times less data
 * than the source.
 */
const MAX_EDGE = 256;

const SCAN_DIRS = [
    'public/assets/icons',
    'public/assets/images',
];

/** Below this there is nothing worth converting. */
const MIN_BYTES = 20 * 1024;

$root = dirname(__DIR__, 2) . '/website';
$apply = in_array('--apply', $argv, true);

if ($apply && !imageDerivativesAvailable()) {
    fwrite(STDERR, "gd is required to convert. Try: php -d extension=gd tools/derasterize-svg.php --apply\n");
    exit(1);
}

$found = 0;
$beforeTotal = 0;
$afterTotal = 0;

$files = [];

foreach (SCAN_DIRS as $relative) {
    $dir = $root . '/' . $relative;

    if (!is_dir($dir)) {
        continue;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    ) as $entry) {
        $files[] = $entry;
    }
}

/** @var SplFileInfo $file */
foreach ($files as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'svg') {
        continue;
    }

    if ($file->getSize() < MIN_BYTES) {
        continue;
    }

    $svg = file_get_contents($file->getPathname());

    // A genuine vector file with an embedded raster somewhere in it is a
    // different thing and must be left alone, so require that the only drawing
    // element present is the <image>.
    if (preg_match('/<(path|rect|circle|ellipse|polygon|polyline|line|text)\b/i', $svg) === 1) {
        continue;
    }

    if (preg_match('/xlink:href="data:image\/(png|jpe?g|webp);base64,([^"]+)"/i', $svg, $m) !== 1) {
        continue;
    }

    $found++;

    $binary = base64_decode(preg_replace('/\s+/', '', $m[2]) ?? '', true);
    if ($binary === false) {
        printf("  ! %s — base64 did not decode\n", $file->getFilename());
        continue;
    }

    $target = preg_replace('/\.svg$/i', '.png', $file->getPathname()) ?? '';
    $before = $file->getSize();
    $beforeTotal += $before;

    if (!$apply) {
        $size = @getimagesizefromstring($binary);
        printf(
            "  %-32s %6s KB  %s  ->  %s.png\n",
            $file->getFilename(),
            number_format($before / 1024, 0),
            $size !== false ? $size[0] . 'x' . $size[1] : 'unknown',
            pathinfo($file->getFilename(), PATHINFO_FILENAME)
        );
        continue;
    }

    $img = @imagecreatefromstring($binary);
    if ($img === false) {
        printf("  ! %s — gd could not read the embedded image\n", $file->getFilename());
        continue;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1.0, MAX_EDGE / max($w, $h));

    $outW = max(1, (int) round($w * $scale));
    $outH = max(1, (int) round($h * $scale));

    $out = resampleImage($img, $outW, $outH);
    imagedestroy($img);

    if ($out === null) {
        printf("  ! %s — resample failed\n", $file->getFilename());
        continue;
    }

    imagesavealpha($out, true);
    $ok = imagepng($out, $target, 9);
    imagedestroy($out);

    if (!$ok) {
        printf("  ! %s — could not write %s\n", $file->getFilename(), basename($target));
        continue;
    }

    $after = filesize($target);
    $afterTotal += $after;

    printf(
        "  + %-32s %6s KB (%dx%d)  ->  %-26s %5s KB (%dx%d)   -%.0f%%\n",
        $file->getFilename(),
        number_format($before / 1024, 0),
        $w,
        $h,
        basename($target),
        number_format($after / 1024, 1),
        $outW,
        $outH,
        (1 - $after / $before) * 100
    );
}

printf("\n%d raster-in-SVG file(s) found.\n", $found);

if ($apply && $beforeTotal > 0) {
    printf(
        "%s KB -> %s KB  (%.0f%% smaller, before the WebP twin)\n",
        number_format($beforeTotal / 1024, 0),
        number_format($afterTotal / 1024, 1),
        (1 - $afterTotal / $beforeTotal) * 100
    );
    echo "\nNext: update the markup to point at the .png files, then rerun\n";
    echo "extras/tools/optimize-images.php to generate their WebP twins.\n";
} elseif (!$apply && $found > 0) {
    echo "\nPass --apply to convert.\n";
}
