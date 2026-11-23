<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('media.upload', 'index.php');
requirePostWithCsrf('upload.php');

$user = currentUser();
$files = $_FILES['files'] ?? null;
$added = [];
$errors = [];

if (!is_array($files) || !is_array($files['name'] ?? null)) {
    flashSet('error', 'Choose at least one file to upload.');
    redirect('upload.php');
}

foreach (array_keys($files['name']) as $n) {
    if (($files['error'][$n] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    if (count($added) >= 12) {
        $errors[] = 'Only 12 files can be uploaded at once; upload the rest separately.';
        break;
    }

    // Each file is handed to the shared upload code as a single upload of
    // its own, so it gets the same checks as every other upload in the
    // admin: real content type (not the name or what the browser claims),
    // size cap, a generated file name, and for photos the metadata strip and
    // the smaller copies.
    $_FILES['__media_file'] = [
        'name' => $files['name'][$n],
        'type' => $files['type'][$n],
        'tmp_name' => $files['tmp_name'][$n],
        'error' => $files['error'][$n],
        'size' => $files['size'][$n],
    ];

    $isPdf = false;
    if (is_uploaded_file((string) $files['tmp_name'][$n])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $isPdf = finfo_file($finfo, $files['tmp_name'][$n]) === 'application/pdf';
        finfo_close($finfo);
    }

    $upload = $isPdf
        ? handleDocumentUpload('__media_file', MEDIA_LIBRARY_FOLDER)
        : handleImageUpload('__media_file', MEDIA_LIBRARY_FOLDER);

    if ($upload['ok'] && $upload['path'] !== null) {
        $added[] = $upload['path'];
    } else {
        $errors[] = $files['name'][$n] . ': ' . ($upload['error'] ?? 'not saved');
    }
}
unset($_FILES['__media_file']);

if ($added) {
    logActivity($user['id'], 'create', 'media', count($added) . ' file(s) uploaded to the library');
}

if ($errors) {
    flashSet('error', ($added ? count($added) . ' uploaded. ' : '') . implode(' ', $errors));
    redirect($added ? 'index.php?folder=' . MEDIA_LIBRARY_FOLDER : 'upload.php');
}

flashSet('success', count($added) . ' file' . (count($added) === 1 ? '' : 's') . ' uploaded to the Library.');
redirect('index.php?folder=' . MEDIA_LIBRARY_FOLDER);
