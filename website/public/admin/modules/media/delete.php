<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('media.delete');

requirePostWithCsrf();

$path = (string) ($_POST['path'] ?? '');

if ($path === '' || mediaFileFromPath($path) === null) {
    flashSet('error', 'That file could not be found.');
    redirect('index.php');
}

// Re-check against the database here too, not just trusting that the
// list page only showed a delete button for orphaned files — a request
// could in principle be replayed or edited before submission.
$referenced = referencedUploadPaths();

if (isset($referenced[$path])) {
    flashSet('error', 'That file is still in use and cannot be deleted from here.');
    redirect('index.php');
}

// Also removes the smaller copies made from it.
deleteUploadedFile($path);
logActivity(currentUser()['id'], 'delete', 'media', $path);
flashSet('success', 'File deleted.');
redirect('index.php');
