<?php

declare(strict_types=1);

/**
 * Generation of the WebP twins and width variants the site serves.
 *
 * Used from two places, deliberately sharing one implementation:
 *
 *   - extras/tools/optimize-images.php, over the whole tree, as a build step.
 *   - public/admin/includes/upload.php, for a single file, the moment an
 *     admin uploads it.
 *
 * The second is what keeps the optimisation from decaying. Without it, every
 * photo added through the CMS after this was written would be served as an
 * un-optimised original — the content-negotiation rule in public/.htaccess
 * only rewrites to a .webp that exists — and the site would quietly get slower
 * with each upload until somebody remembered to rerun the tool.
 *
 * ---------------------------------------------------------------------------
 * Everything here degrades quietly
 * ---------------------------------------------------------------------------
 * If gd is missing or an encode fails, these functions return 0 and the caller
 * carries on. A visitor then gets the original JPEG or PNG, exactly as before
 * this file existed. An upload must never fail because an optimisation could
 * not be produced — the photo is the thing the admin cares about, and the
 * derivative is an optimisation of it.
 *
 * That matters concretely here: gd is commented out in this XAMPP's php.ini,
 * so locally every call is a no-op, while Hostinger has gd enabled by default
 * and the same code produces derivatives on the live site.
 */

/** Widths generated for srcset. Kept in step with imageSrcset() in assets.php. */
const DERIVATIVE_WIDTHS = [480, 768, 1200, 1600];

/**
 * Only upscale-free variants are written: a source must be at least this many
 * times wider than a variant for that variant to be worth having.
 */
const DERIVATIVE_WIDTH_MARGIN = 1.15;

const DERIVATIVE_WEBP_QUALITY = 82;
const DERIVATIVE_JPEG_QUALITY = 82;

/** Below this, the derivative would not pay for the extra request or the disk. */
const DERIVATIVE_MIN_SOURCE_BYTES = 8 * 1024;

/**
 * True when this PHP can actually encode WebP.
 *
 * gd can be compiled without WebP support, so the extension being loaded is
 * not on its own enough to know.
 */
function imageDerivativesAvailable(): bool
{
    return function_exists('imagewebp') && function_exists('imagecreatetruecolor');
}

/**
 * Writes the WebP twin and width variants for one image.
 *
 * @param string $absPath Absolute path to the source image.
 * @param bool   $force   Rebuild even when a derivative is newer than the source.
 * @return int Number of files written.
 */
function generateImageDerivatives(string $absPath, bool $force = false): int
{
    if (!imageDerivativesAvailable() || !is_file($absPath)) {
        return 0;
    }

    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return 0;
    }

    // Never treat a file this code produced as a source; that would generate
    // photo-768w-480w.jpg and multiply on every run.
    if (isImageDerivative($absPath)) {
        return 0;
    }

    if (filesize($absPath) < DERIVATIVE_MIN_SOURCE_BYTES) {
        return 0;
    }

    $size = @getimagesize($absPath);
    if ($size === false) {
        return 0;
    }

    [$srcWidth, $srcHeight] = $size;
    $written = 0;

    // Full-size twin. A source that is already WebP needs none: it would be a
    // duplicate under a longer name that .htaccess would never reach for.
    if ($ext !== 'webp') {
        $twin = $absPath . '.webp';
        if (derivativeIsStale($twin, $absPath, $force) && writeWebpDerivative($absPath, $twin, null, null)) {
            $written++;
        }
    }

    foreach (DERIVATIVE_WIDTHS as $width) {
        if ($srcWidth < $width * DERIVATIVE_WIDTH_MARGIN) {
            continue;
        }

        $height = (int) round($srcHeight * ($width / $srcWidth));
        $variant = imageVariantPath($absPath, $width);

        if (derivativeIsStale($variant, $absPath, $force) && writeResizedDerivative($absPath, $variant, $width, $height)) {
            $written++;
        }

        if ($ext === 'webp') {
            continue;
        }

        $variantTwin = $variant . '.webp';
        if (derivativeIsStale($variantTwin, $absPath, $force) && writeWebpDerivative($absPath, $variantTwin, $width, $height)) {
            $written++;
        }
    }

    return $written;
}

/**
 * Removes every derivative of an image.
 *
 * Called when the source is deleted. Without this, replacing a team photo
 * would leave its old twins on disk forever, and — worse — a later upload that
 * happened to reuse the filename would be served the previous photo's WebP by
 * the content-negotiation rule, showing the wrong person.
 */
function deleteImageDerivatives(string $absPath): void
{
    $candidates = [$absPath . '.webp'];

    foreach (DERIVATIVE_WIDTHS as $width) {
        $variant = imageVariantPath($absPath, $width);
        $candidates[] = $variant;
        $candidates[] = $variant . '.webp';
    }

    foreach ($candidates as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** "photo-768w.jpg" for width 768. */
function imageVariantPath(string $source, int $width): string
{
    $dir = dirname($source);
    $ext = pathinfo($source, PATHINFO_EXTENSION);
    $base = pathinfo($source, PATHINFO_FILENAME);

    return "{$dir}/{$base}-{$width}w.{$ext}";
}

/** True for a file this code generated. */
function isImageDerivative(string $path): bool
{
    $name = basename($path);

    return preg_match('/\.(jpg|jpeg|png)\.webp$/i', $name) === 1
        || preg_match('/-\d+w\.(jpg|jpeg|png|webp)$/i', $name) === 1;
}

function derivativeIsStale(string $target, string $source, bool $force): bool
{
    if ($force || !is_file($target)) {
        return true;
    }

    return filemtime($target) < filemtime($source);
}

function writeWebpDerivative(string $source, string $target, ?int $width, ?int $height): bool
{
    $img = loadImageResource($source);
    if ($img === null) {
        return false;
    }

    if ($width !== null && $height !== null) {
        $resized = resampleImage($img, $width, $height);
        imagedestroy($img);
        if ($resized === null) {
            return false;
        }
        $img = $resized;
    }

    // Without these three, the transparent areas of a PNG logo are written out
    // as solid black.
    imagepalettetotruecolor($img);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $ok = @imagewebp($img, $target, DERIVATIVE_WEBP_QUALITY);
    imagedestroy($img);

    return $ok;
}

function writeResizedDerivative(string $source, string $target, int $width, int $height): bool
{
    $img = loadImageResource($source);
    if ($img === null) {
        return false;
    }

    $out = resampleImage($img, $width, $height);
    imagedestroy($img);

    if ($out === null) {
        return false;
    }

    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));

    $ok = match ($ext) {
        'png' => @imagepng($out, $target, 8),
        'webp' => @imagewebp($out, $target, DERIVATIVE_WEBP_QUALITY),
        default => @imagejpeg($out, $target, DERIVATIVE_JPEG_QUALITY),
    };

    imagedestroy($out);

    return $ok;
}

function loadImageResource(string $path): ?GdImage
{
    $img = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'png' => @imagecreatefrompng($path),
        'webp' => @imagecreatefromwebp($path),
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        default => false,
    };

    return $img === false ? null : $img;
}

function resampleImage(GdImage $src, int $width, int $height): ?GdImage
{
    $out = @imagecreatetruecolor($width, $height);
    if ($out === false) {
        return null;
    }

    // Prepared before the copy, not after: imagecopyresampled blends onto the
    // destination, so a transparent source over an unprepared canvas comes out
    // with black fringing along every antialiased edge.
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));

    imagecopyresampled(
        $out,
        $src,
        0, 0, 0, 0,
        $width, $height,
        imagesx($src), imagesy($src)
    );

    return $out;
}
