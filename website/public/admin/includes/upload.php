<?php
/**
 * Secure file upload handling for admin content modules. Every upload
 * goes through handleFileUpload() — real MIME sniffing via finfo (never
 * trusts the client-supplied $_FILES[...]['type']), a caller-supplied
 * MIME allow-list and size cap, and a generated filename (never the
 * client's original filename) written into admin/uploads/<subfolder>/.
 * handleImageUpload() and handleDocumentUpload() are thin wrappers over
 * it with their own allow-lists — handleImageUpload()'s behavior is
 * unchanged from before this file had more than one upload kind, so
 * every existing caller (Programs, Team, Gallery) needed zero changes.
 */

// WebP twins and responsive width variants for uploaded images. Shared with
// extras/tools/optimize-images.php so the CMS and the build step cannot produce
// different results from the same photo.
require_once __DIR__ . '/../../../includes/image-derivatives.php';

const UPLOAD_IMAGE_MAX_BYTES = 5 * 1024 * 1024; // 5MB
const UPLOAD_IMAGE_ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

const UPLOAD_DOCUMENT_MAX_BYTES = 15 * 1024 * 1024; // 15MB — reports can be image-heavy multi-page PDFs
const UPLOAD_DOCUMENT_ALLOWED_MIME = [
    'application/pdf' => 'pdf',
];

/**
 * @return array{ok: bool, path: ?string, error: ?string}
 *   path is relative to admin/uploads/ (e.g. "programs/3f9c1a2b7e4d5f6a.jpg"),
 *   suitable for storing directly in the DB and for building a URL of
 *   "uploads/{$path}" from within the admin app. If no file was
 *   submitted, returns ok=true with $oldRelativePath unchanged, so a form
 *   save without a new upload keeps whatever file already existed.
 */
function handleFileUpload(
    string $fieldName,
    string $subfolder,
    array $allowedMime,
    int $maxBytes,
    ?string $oldRelativePath,
    string $errorLabel,
    bool $removeRequested = false
): array {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        // No new file. Either the caller asked to clear the existing one
        // (the "Remove current photo" checkbox on the edit forms) or the
        // save simply didn't touch the file, in which case whatever was
        // there stays. Before this, there was no way to remove a photo at
        // all — only to replace it with a different one.
        if ($removeRequested && $oldRelativePath !== null) {
            deleteUploadedFile($oldRelativePath);
            return ['ok' => true, 'path' => null, 'error' => null];
        }

        return ['ok' => true, 'path' => $oldRelativePath, 'error' => null];
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'error' => 'Upload failed (error code ' . $file['error'] . ').'];
    }

    if ($file['size'] > $maxBytes) {
        $maxMb = (int) ($maxBytes / (1024 * 1024));
        return ['ok' => false, 'path' => null, 'error' => "File must be smaller than {$maxMb}MB."];
    }

    // Real MIME sniffing — never trust $file['type'], which is client-supplied
    // and trivially spoofable (e.g. renaming shell.php to shell.jpg).
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMime[$mime])) {
        return ['ok' => false, 'path' => null, 'error' => "Only {$errorLabel} files are allowed."];
    }

    $extension = $allowedMime[$mime];
    $safeName = bin2hex(random_bytes(8)) . '.' . $extension;
    $relativePath = $subfolder . '/' . $safeName;

    $targetDir = __DIR__ . '/../uploads/' . $subfolder;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not create the upload directory.'];
    }

    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $safeName)) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not save the uploaded file.'];
    }

    // Before any variant is built from it: drop camera metadata (GPS position,
    // device, timestamps) from JPEGs, which would otherwise be published with
    // the original file.
    if ($mime === 'image/jpeg') {
        stripJpegMetadata($targetDir . '/' . $safeName);
    }

    // Build the WebP twin and the responsive width variants for this file now,
    // so a photo added through the CMS is served as efficiently as one that
    // shipped with the repository. Without this the site would get slower with
    // every upload: public/.htaccess only serves a .webp that exists on disk,
    // so a new photo would silently fall back to its full-size original.
    //
    // A no-op for PDFs and for any host without gd, and deliberately not
    // error-checked — see includes/image-derivatives.php. The upload has
    // already succeeded at this point and must not be reported as failed
    // because an optimisation could not be generated.
    generateImageDerivatives($targetDir . '/' . $safeName);

    if ($oldRelativePath) {
        deleteUploadedFile($oldRelativePath);
    }

    return ['ok' => true, 'path' => $relativePath, 'error' => null];
}

/**
 * Re-saves a JPEG without its EXIF/XMP metadata when it carries any.
 *
 * Photos straight from a phone embed where they were taken; uploaded
 * originals are served as-is, so for a charity photographing children at
 * school sites that is a real disclosure. The EXIF orientation is applied to
 * the pixels first - phones store "rotate 90°" as metadata, and stripping it
 * alone would publish the photo sideways.
 *
 * Needs gd; without it (or if anything fails) the file is left untouched, the
 * same fail-open rule as generateImageDerivatives(): the upload has already
 * succeeded and an optimisation must not undo it. Files with no metadata
 * block are not re-encoded, so there is no needless quality loss.
 */
function stripJpegMetadata(string $path): void
{
    if (!function_exists('imagecreatefromjpeg') || !is_file($path)) {
        return;
    }

    $head = (string) file_get_contents($path, false, null, 0, 65536);
    if (!str_contains($head, 'Exif') && !str_contains($head, 'http://ns.adobe.com/xap/')) {
        return;
    }

    try {
        $image = @imagecreatefromjpeg($path);
        if ($image === false) {
            return;
        }

        $orientation = 1;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = (int) ($exif['Orientation'] ?? 1);
        }

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
        if ($rotated !== false && $rotated !== $image) {
            imagedestroy($image);
            $image = $rotated;
        }

        // Write beside the original and swap, so a failed write never leaves
        // a truncated file in place of the upload.
        $tmp = $path . '.tmp';
        if (imagejpeg($image, $tmp, 90)) {
            rename($tmp, $path);
        } else {
            @unlink($tmp);
        }
        imagedestroy($image);
    } catch (Throwable $e) {
        adminLog('WARN', 'could not strip JPEG metadata: ' . $e->getMessage());
    }
}

function handleImageUpload(
    string $fieldName,
    string $subfolder,
    ?string $oldRelativePath = null,
    bool $removeRequested = false
): array {
    return handleFileUpload(
        $fieldName,
        $subfolder,
        UPLOAD_IMAGE_ALLOWED_MIME,
        UPLOAD_IMAGE_MAX_BYTES,
        $oldRelativePath,
        'JPEG, PNG, or WebP image',
        $removeRequested
    );
}

function handleDocumentUpload(
    string $fieldName,
    string $subfolder,
    ?string $oldRelativePath = null,
    bool $removeRequested = false
): array {
    return handleFileUpload(
        $fieldName,
        $subfolder,
        UPLOAD_DOCUMENT_ALLOWED_MIME,
        UPLOAD_DOCUMENT_MAX_BYTES,
        $oldRelativePath,
        'PDF',
        $removeRequested
    );
}

/**
 * The "Remove current file" checkbox for an edit form. Renders nothing on
 * a create form or when there's no file yet — there'd be nothing to
 * remove. $targetId matches the wrapper element the JS dims when ticked.
 */
function removeFileCheckbox(string $fieldName, ?string $currentPath, string $targetId, string $label = 'Remove current photo'): string
{
    if (!$currentPath) {
        return '';
    }

    return '<label class="remove-file">'
        . '<input type="checkbox" name="remove_' . e($fieldName) . '" value="1" data-remove-toggle="' . e($targetId) . '">'
        . '<span>' . e($label) . '</span>'
        . '</label>';
}

/**
 * Deletes a previously uploaded file by its DB-stored relative path.
 * Resolves the real path first and refuses to delete anything outside
 * admin/uploads/ — a defensive guard against path traversal if a
 * relative path were ever crafted with "../" segments.
 */
function deleteUploadedFile(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    $resolved = realpath(__DIR__ . '/../uploads/' . $relativePath);

    // Compare against the root *plus a separator* — a bare prefix check
    // would treat a sibling directory like admin/uploads_old/ as being
    // inside admin/uploads/, since the string starts the same way.
    if ($resolved !== false && $uploadsRoot !== false
        && str_starts_with($resolved, rtrim($uploadsRoot, '/\\') . DIRECTORY_SEPARATOR)) {
        // Derivatives first, while $resolved is still known to be a real path
        // inside uploads/. Leaving them behind would not just waste disk: the
        // filenames here are random, but if one were ever reused the
        // content-negotiation rule in public/.htaccess would serve the
        // previous image's WebP twin in place of the new photo.
        deleteImageDerivatives($resolved);

        @unlink($resolved);
    }
}
