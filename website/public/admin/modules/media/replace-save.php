<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('media.upload', 'index.php');
requirePostWithCsrf();

$user = currentUser();
$path = (string) ($_POST['path'] ?? '');
$abs = mediaFileFromPath($path);
$kind = mediaKind($path);
$back = 'replace.php?path=' . rawurlencode($path);

if ($abs === null || !in_array($kind, ['image', 'pdf'], true)) {
    flashSet('error', 'That file could not be found.');
    redirect('index.php');
}

// The new file is stored under a NEW name and every reference is switched
// to it, rather than overwriting the old file in place. Photos are cached by
// browsers for six months (public/.htaccess): a picture swapped in under
// the same URL would keep showing the old one to returning visitors. A new
// URL is picked up at once.
$folder = str_contains($path, '/') ? dirname($path) : MEDIA_LIBRARY_FOLDER;
$upload = $kind === 'pdf'
    ? handleDocumentUpload('file', $folder)
    : handleImageUpload('file', $folder);

if (!$upload['ok'] || $upload['path'] === null) {
    flashSet('error', $upload['error'] ?? 'Choose a file to upload.');
    redirect($back);
}

try {
    $changed = replaceUploadReferences($path, $upload['path']);
} catch (Throwable $e) {
    // Nothing points at the new file: remove it and keep the old one.
    deleteUploadedFile($upload['path']);
    throw $e;
}

// Removes the old file and every smaller copy made from it.
deleteUploadedFile($path);

logActivity($user['id'], 'update', 'media', sprintf('Replaced %s with %s (%d place%s updated)', $path, $upload['path'], $changed, $changed === 1 ? '' : 's'));
flashSet('success', $changed > 0
    ? sprintf('File replaced. %d place%s on the website now show%s the new file.', $changed, $changed === 1 ? '' : 's', $changed === 1 ? 's' : '')
    : 'File replaced.');
redirect('index.php');
